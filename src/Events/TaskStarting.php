<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Events;

use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;

final readonly class TaskStarting
{
    public function __construct(
        public Task $task,
        public TaskOptions $options,
    ) {}
}
