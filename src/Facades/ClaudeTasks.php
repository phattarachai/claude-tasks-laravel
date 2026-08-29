<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Override;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksManager;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Testing\FakeClaudeTasksManager;

/**
 * @method static TaskResponse run(Task $task, Closure|null $onProgress = null)
 * @method static QueuedTaskResponse queue(Task $task)
 * @method static FakeClaudeTasksManager withProgress(string $task, array $events)
 * @method static void assertRan(string $task, Closure|null $callback = null)
 * @method static void assertQueued(string $task, Closure|null $callback = null)
 * @method static void assertRanTimes(string $task, int $times = 1)
 * @method static void assertNothingRan()
 *
 * @see ClaudeTasksManager
 */
class ClaudeTasks extends Facade
{
    /**
     * @param  array<class-string<Task>, array<string, mixed>|Closure>  $outputs
     * @param  array<class-string<Task>, list<ProgressEvent>>  $progress
     */
    public static function fake(array $outputs = [], array $progress = []): FakeClaudeTasksManager
    {
        $fake = new FakeClaudeTasksManager($outputs, $progress);

        static::swap($fake);

        return $fake;
    }

    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return ClaudeTasksManager::class;
    }
}
