<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Events\TaskCompleted;
use Phattarachai\ClaudeTasksLaravel\Events\TaskFailed;
use Phattarachai\ClaudeTasksLaravel\Events\TaskStarting;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeAuthExpired;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Mcp\McpConfigFile;
use Phattarachai\ClaudeTasksLaravel\Prompt\PromptComposer;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Schema\OutputValidator;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Streaming\StreamParser;
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;
use Phattarachai\ClaudeTasksLaravel\TaskRuns\TaskRunRecorder;
use Throwable;

/**
 * One headless run: compose prompt, invoke the CLI, parse the JSON envelope,
 * validate against the Task's schema. Failures always throw — error text is never
 * returned as a result.
 *
 * A progress listener switches the run to `--output-format stream-json`: the same
 * process, read line by line, with events delivered as they arrive. The schema gate,
 * the auth/process failure classification, and the timeout are identical either way.
 */
class TaskRunner
{
    public function __construct(
        private readonly ClaudeBinary $binary,
        private readonly ClaudeAuth $auth,
        private readonly PromptComposer $composer,
        private readonly ResponseParser $parser,
        private readonly OutputValidator $validator,
        private readonly TaskRunRecorder $recorder,
    ) {}

    /**
     * @param  (Closure(ProgressEvent): void)|null  $onProgress
     */
    public function run(Task $task, ?Closure $onProgress = null): TaskResponse
    {
        $options = TaskOptions::resolve($task);

        event(new TaskStarting($task, $options));

        $run = $this->recorder->start($task, $options);

        try {
            $response = $this->execute($task, $options, $onProgress);

            $this->recorder->success($run, $response->usage);

            event(new TaskCompleted($task, $response));

            return $response;
        } catch (Throwable $exception) {
            $this->recorder->failure($run, $exception);

            event(new TaskFailed($task, $exception));

            throw $exception;
        }
    }

    /**
     * @param  (Closure(ProgressEvent): void)|null  $onProgress
     */
    private function execute(Task $task, TaskOptions $options, ?Closure $onProgress): TaskResponse
    {
        $mcpConfigPath = McpConfigFile::writeFor($task);

        try {
            $parsed = $onProgress === null
                ? $this->parser->parse($this->invoke($task, $options, $mcpConfigPath)->output())
                : $this->stream($task, $options, $mcpConfigPath, $onProgress);
        } finally {
            McpConfigFile::cleanup($mcpConfigPath);
        }

        if ($parsed->isError) {
            throw $this->classifyFailure($parsed->text, ClaudeProcessFailed::errorResult($parsed->text));
        }

        return new TaskResponse(
            output: $this->validator->validate($task, $parsed->text),
            usage: $parsed->usage,
            text: $parsed->text,
        );
    }

    private function invoke(Task $task, TaskOptions $options, ?string $mcpConfigPath): ProcessResult
    {
        $result = $this->pending($options)->run($this->command($task, $options, $mcpConfigPath)->argv);

        $this->ensureSucceeded($result);

        return $result;
    }

    /**
     * Start the CLI, read its newline-delimited events as the pipe delivers them, and
     * keep the closing `result` line as the run's parsed outcome.
     *
     * @param  Closure(ProgressEvent): void  $onProgress
     */
    private function stream(Task $task, TaskOptions $options, ?string $mcpConfigPath, Closure $onProgress): ParsedResult
    {
        $streamParser = new StreamParser($this->parser);
        $result = null;

        /** @param list<ProgressEvent> $events */
        $deliver = function (array $events) use ($onProgress, &$result): void {
            foreach ($events as $event) {
                $result = $event instanceof ResultReceived ? $event : $result;

                $onProgress($event);
            }
        };

        $invoked = $this->pending($options)->start(
            $this->command($task, $options, $mcpConfigPath, streaming: true)->argv,
            function (string $type, string $chunk) use ($streamParser, $deliver): void {
                $deliver($type === 'out' ? $streamParser->push($chunk) : []);
            },
        );

        $processResult = $invoked->wait();

        $deliver($streamParser->flush());

        $this->ensureSucceeded($processResult);

        if (! $result instanceof ResultReceived) {
            throw ClaudeProcessFailed::unparseableOutput($processResult->output());
        }

        return new ParsedResult($result->text, $result->usage, $result->isError);
    }

    private function pending(TaskOptions $options): PendingProcess
    {
        return Process::path(base_path())
            ->timeout($options->timeout)
            ->env(['CLAUDECODE' => false, 'AI_AGENT' => false]);
    }

    private function command(Task $task, TaskOptions $options, ?string $mcpConfigPath, bool $streaming = false): ClaudeCommand
    {
        return ClaudeCommand::build($this->binary->path(), $this->composer->compose($task), $options, $mcpConfigPath, $streaming);
    }

    private function ensureSucceeded(ProcessResult $result): void
    {
        if (! $result->failed()) {
            return;
        }

        $output = trim($result->errorOutput()."\n".$result->output());

        throw $this->classifyFailure($output, ClaudeProcessFailed::fromResult($result));
    }

    private function classifyFailure(string $output, ClaudeProcessFailed $fallback): ClaudeProcessFailed|ClaudeAuthExpired
    {
        return $this->auth->looksLikeAuthFailure($output)
            ? ClaudeAuthExpired::fromOutput($output)
            : $fallback;
    }
}
