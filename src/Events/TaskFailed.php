<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Events;

use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Throwable;

final readonly class TaskFailed
{
    public function __construct(
        public Task $task,
        public Throwable $exception,
    ) {}
}
