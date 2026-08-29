<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Events;

use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;

final readonly class TaskCompleted
{
    public function __construct(
        public Task $task,
        public TaskResponse $response,
    ) {}
}
