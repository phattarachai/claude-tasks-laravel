<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel;

use Closure;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Jobs\RunClaudeTask;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Runner\TaskRunner;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;

class ClaudeTasksManager
{
    /**
     * @param  (Closure(ProgressEvent): void)|null  $onProgress  a listener switches the run to streaming mode
     */
    public function run(Task $task, ?Closure $onProgress = null): TaskResponse
    {
        return app(TaskRunner::class)->run($task, $onProgress);
    }

    public function queue(Task $task): QueuedTaskResponse
    {
        return new QueuedTaskResponse(RunClaudeTask::dispatch($task));
    }
}
