<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel;

use Closure;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;

/**
 * A Task with a progress listener attached. `onProgress()` returns this instead of
 * storing the Closure on the Task, so the Task stays queue-serializable — a Closure
 * is not, and `SerializesModels` would choke on it the moment someone queued the run.
 *
 * There is deliberately no `queue()` here: a listener only makes sense in the process
 * that is watching. Queue a job of your own and call `onProgress()->run()` inside it.
 */
final readonly class PendingRun
{
    /**
     * @param  Closure(ProgressEvent): void  $onProgress
     */
    public function __construct(private Task $task, private Closure $onProgress) {}

    public function run(): TaskResponse
    {
        return ClaudeTasks::run($this->task, $this->onProgress);
    }
}
