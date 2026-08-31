<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Testing;

use Closure;
use Illuminate\Support\Collection;
use Override;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksManager;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Contracts\UsesMcpServers;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Support\RunManifest;
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;
use PHPUnit\Framework\Assert as PHPUnit;

class FakeClaudeTasksManager extends ClaudeTasksManager
{
    /**
     * @var array<class-string, list<Task>>
     */
    protected array $ran = [];

    /**
     * @var array<class-string, list<Task>>
     */
    protected array $queued = [];

    /**
     * @param  array<class-string<Task>, array<string, mixed>|string|Closure>  $outputs  a string cans a Format::Text reply
     * @param  array<class-string<Task>, list<ProgressEvent>>  $progress
     */
    public function __construct(protected array $outputs = [], protected array $progress = []) {}

    /**
     * Canned progress events for a Task, replayed in order to whatever listener the
     * run attaches. A closing {@see ResultReceived} is appended unless the sequence
     * already carries one, so a fake ends the way a real stream does.
     *
     * @param  class-string<Task>  $task
     * @param  list<ProgressEvent>  $events
     */
    public function withProgress(string $task, array $events): static
    {
        $this->progress[$task] = $events;

        return $this;
    }

    #[Override]
    public function run(Task $task, ?Closure $onProgress = null): TaskResponse
    {
        $this->ran[$task::class][] = $task;

        $response = $this->responseFor($task);

        if ($onProgress !== null) {
            $this->replayProgress($task, $response, $onProgress);
        }

        return $response;
    }

    #[Override]
    public function queue(Task $task): QueuedTaskResponse
    {
        $this->queued[$task::class][] = $task;

        return new QueuedTaskResponse;
    }

    /**
     * @param  class-string  $task
     */
    public function assertRan(string $task, ?Closure $callback = null): void
    {
        $recorded = new Collection([...($this->ran[$task] ?? []), ...($this->queued[$task] ?? [])]);

        PHPUnit::assertTrue(
            $recorded->contains(fn (Task $recordedTask): bool => $callback === null || (bool) $callback($recordedTask)),
            "The expected [{$task}] task was not run.",
        );
    }

    /**
     * @param  class-string  $task
     */
    public function assertQueued(string $task, ?Closure $callback = null): void
    {
        $recorded = new Collection($this->queued[$task] ?? []);

        PHPUnit::assertTrue(
            $recorded->contains(fn (Task $recordedTask): bool => $callback === null || (bool) $callback($recordedTask)),
            "The expected [{$task}] task was not queued.",
        );
    }

    /**
     * @param  class-string  $task
     */
    public function assertRanTimes(string $task, int $times = 1): void
    {
        $count = count($this->ran[$task] ?? []) + count($this->queued[$task] ?? []);

        PHPUnit::assertSame($times, $count, "The [{$task}] task ran {$count} times instead of {$times}.");
    }

    public function assertNothingRan(): void
    {
        PHPUnit::assertSame([], $this->ran, 'Tasks were run unexpectedly.');
        PHPUnit::assertSame([], $this->queued, 'Tasks were queued unexpectedly.');
    }

    /**
     * @param  Closure(ProgressEvent): void  $onProgress
     */
    private function replayProgress(Task $task, TaskResponse $response, Closure $onProgress): void
    {
        $configured = $this->progress[$task::class] ?? [];

        $events = new Collection($configured)->contains(fn (ProgressEvent $event): bool => $event instanceof ResultReceived)
            ? $configured
            : [...$configured, new ResultReceived($response->text, $response->usage)];

        foreach ($events as $event) {
            $onProgress($event);
        }
    }

    private function responseFor(Task $task): TaskResponse
    {
        $canned = $this->outputs[$task::class] ?? null;

        $resolved = $canned instanceof Closure ? $canned($task) : $canned;
        $manifest = $this->fakeManifest($task);

        if (Format::for($task) === Format::Text) {
            $text = is_string($resolved) ? $resolved : FakeOutput::textFor($task);

            return new TaskResponse([], new Usage, $text, $text, $manifest);
        }

        /** @var array<string, mixed> $output */
        $output = $resolved ?? FakeOutput::forTask($task);

        return new TaskResponse($output, new Usage, (string) json_encode($output), '', $manifest);
    }

    private function fakeManifest(Task $task): RunManifest
    {
        $options = TaskOptions::resolve($task);

        return new RunManifest(
            prompt: '[faked prompt]',
            requestedModel: $options->model,
            timeout: $options->timeout,
            maxTurns: $options->maxTurns,
            allowedTools: $options->allowedTools,
            mcpServers: $task instanceof UsesMcpServers ? array_keys($task->mcpServers()) : [],
            outputFormat: 'json',
            responseFormat: $options->responseFormat,
        );
    }
}
