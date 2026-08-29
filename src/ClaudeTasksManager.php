<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel;

use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Jobs\RunClaudeTask;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Runner\TaskRunner;

class ClaudeTasksManager
{
    public function run(Task $task): TaskResponse
    {
        return app(TaskRunner::class)->run($task);
    }

    public function queue(Task $task): QueuedTaskResponse
    {
        return new QueuedTaskResponse(RunClaudeTask::dispatch($task));
    }
}
