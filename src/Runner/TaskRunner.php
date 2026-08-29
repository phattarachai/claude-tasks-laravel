<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Illuminate\Contracts\Process\ProcessResult;
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
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;
use Phattarachai\ClaudeTasksLaravel\TaskRuns\TaskRunRecorder;
use Throwable;

/**
 * One synchronous headless run: compose prompt, invoke the CLI, parse the JSON
 * envelope, validate against the Task's schema. Failures always throw — error
 * text is never returned as a result.
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

    public function run(Task $task): TaskResponse
    {
        $options = TaskOptions::resolve($task);

        event(new TaskStarting($task, $options));

        $run = $this->recorder->start($task, $options);

        try {
            $response = $this->execute($task, $options);

            $this->recorder->success($run, $response->usage);

            event(new TaskCompleted($task, $response));

            return $response;
        } catch (Throwable $exception) {
            $this->recorder->failure($run, $exception);

            event(new TaskFailed($task, $exception));

            throw $exception;
        }
    }

    private function execute(Task $task, TaskOptions $options): TaskResponse
    {
        $mcpConfigPath = McpConfigFile::writeFor($task);

        try {
            $result = $this->invoke($task, $options, $mcpConfigPath);
        } finally {
            McpConfigFile::cleanup($mcpConfigPath);
        }

        $parsed = $this->parser->parse($result->output());

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
        $command = ClaudeCommand::build($this->binary->path(), $this->composer->compose($task), $options, $mcpConfigPath);

        $result = Process::path(base_path())
            ->timeout($options->timeout)
            ->env(['CLAUDECODE' => false, 'AI_AGENT' => false])
            ->run($command->argv);

        if ($result->failed()) {
            $output = trim($result->errorOutput()."\n".$result->output());

            throw $this->classifyFailure($output, ClaudeProcessFailed::fromResult($result));
        }

        return $result;
    }

    private function classifyFailure(string $output, ClaudeProcessFailed $fallback): ClaudeProcessFailed|ClaudeAuthExpired
    {
        return $this->auth->looksLikeAuthFailure($output)
            ? ClaudeAuthExpired::fromOutput($output)
            : $fallback;
    }
}
