<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Concerns;

use Illuminate\Container\Container;
use Illuminate\Queue\SerializesModels;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;

trait Runnable
{
    use SerializesModels;

    public static function make(mixed ...$arguments): static
    {
        return match (true) {
            $arguments !== [] && ! array_is_list($arguments) => Container::getInstance()->makeWith(static::class, $arguments),
            $arguments !== [] => new static(...$arguments),
            default => Container::getInstance()->make(static::class),
        };
    }

    public function run(): TaskResponse
    {
        return ClaudeTasks::run($this);
    }

    public function queue(): QueuedTaskResponse
    {
        return ClaudeTasks::queue($this);
    }
}
