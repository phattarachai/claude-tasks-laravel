<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Testing;

use Closure;
use Illuminate\Support\Collection;
use Override;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksManager;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
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
     * @param  array<class-string<Task>, array<string, mixed>|Closure>  $outputs
     */
    public function __construct(protected array $outputs = []) {}

    #[Override]
    public function run(Task $task): TaskResponse
    {
        $this->ran[$task::class][] = $task;

        return $this->responseFor($task);
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

    private function responseFor(Task $task): TaskResponse
    {
        $canned = $this->outputs[$task::class] ?? null;

        /** @var array<string, mixed> $output */
        $output = $canned instanceof Closure
            ? $canned($task)
            : ($canned ?? FakeOutput::forTask($task));

        return new TaskResponse($output, new Usage, (string) json_encode($output));
    }
}
