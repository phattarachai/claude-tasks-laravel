<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Contracts\UsesMcpServers;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;
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
use Phattarachai\ClaudeTasksLaravel\Streaming\RunStarted;
use Phattarachai\ClaudeTasksLaravel\Streaming\StreamParser;
use Phattarachai\ClaudeTasksLaravel\Support\RunManifest;
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
        $prompt = $this->composer->compose($task);

        event(new TaskStarting($task, $options));

        $run = $this->recorder->start($task, $options, $this->manifest($task, $options, $prompt, $onProgress !== null));

        try {
            $response = $this->execute($task, $options, $prompt, $onProgress);

            $this->recorder->success($run, $response->usage, $response->request);

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
    private function execute(Task $task, TaskOptions $options, string $prompt, ?Closure $onProgress): TaskResponse
    {
        $mcpConfigPath = McpConfigFile::writeFor($task);
        $streaming = $onProgress !== null;
        $started = null;

        try {
            $parsed = $streaming
                ? $this->stream($options, $prompt, $mcpConfigPath, $onProgress, $started)
                : $this->parser->parse($this->invoke($options, $prompt, $mcpConfigPath)->output());
        } finally {
            McpConfigFile::cleanup($mcpConfigPath);
        }

        if ($parsed->isError) {
            throw $this->classifyFailure($parsed->text, ClaudeProcessFailed::errorResult($parsed->text));
        }

        $manifest = $this->manifest($task, $options, $prompt, $streaming, $parsed, $started);

        if ($options->responseFormat === Format::Text) {
            $narration = $this->validator->ensureText($parsed->text);

            return new TaskResponse([], $parsed->usage, $parsed->text, $narration, $manifest);
        }

        $split = $this->validator->split($task, $parsed->text);

        return new TaskResponse($split->data, $parsed->usage, $parsed->text, $split->narration, $manifest);
    }

    /**
     * What defined this call — prompt + resolved parameters — for the caller and the recorder to log.
     * `actual*` come from the CLI's `system`/`init` line, known only on a streaming run.
     */
    private function manifest(Task $task, TaskOptions $options, string $prompt, bool $streaming, ?ParsedResult $parsed = null, ?RunStarted $started = null): RunManifest
    {
        $sessionId = $parsed?->usage->sessionId;
        $actualModel = null;
        $actualTools = [];

        if ($started instanceof RunStarted) {
            $actualModel = $started->model;
            $actualTools = $started->tools;
            $sessionId = $started->sessionId ?? $sessionId;
        }

        return new RunManifest(
            prompt: $prompt,
            requestedModel: $options->model,
            timeout: $options->timeout,
            maxTurns: $options->maxTurns,
            allowedTools: $options->allowedTools,
            mcpServers: $task instanceof UsesMcpServers ? array_keys($task->mcpServers()) : [],
            outputFormat: $streaming ? 'stream-json' : 'json',
            responseFormat: $options->responseFormat,
            actualModel: $actualModel,
            sessionId: $sessionId,
            actualTools: $actualTools,
        );
    }

    private function invoke(TaskOptions $options, string $prompt, ?string $mcpConfigPath): ProcessResult
    {
        $result = $this->pending($options)->run($this->command($options, $prompt, $mcpConfigPath)->argv);

        $this->ensureSucceeded($result);

        return $result;
    }

    /**
     * Start the CLI, read its newline-delimited events as the pipe delivers them, keep the closing
     * `result` line as the run's parsed outcome, and hand back the opening `system`/`init` line
     * (the actual model / session / tools) through `$started`.
     *
     * @param  Closure(ProgressEvent): void  $onProgress
     */
    private function stream(TaskOptions $options, string $prompt, ?string $mcpConfigPath, Closure $onProgress, ?RunStarted &$started): ParsedResult
    {
        $streamParser = new StreamParser($this->parser);
        $result = null;

        /** @param list<ProgressEvent> $events */
        $deliver = function (array $events) use ($onProgress, &$result, &$started): void {
            foreach ($events as $event) {
                $result = $event instanceof ResultReceived ? $event : $result;
                $started = $event instanceof RunStarted ? $event : $started;

                $onProgress($event);
            }
        };

        $invoked = $this->pending($options)->start(
            $this->command($options, $prompt, $mcpConfigPath, streaming: true)->argv,
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

    private function command(TaskOptions $options, string $prompt, ?string $mcpConfigPath, bool $streaming = false): ClaudeCommand
    {
        return ClaudeCommand::build($this->binary->path(), $prompt, $options, $mcpConfigPath, $streaming);
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
