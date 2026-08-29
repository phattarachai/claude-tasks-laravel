<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Concerns;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Queue\SerializesModels;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\PendingRun;
use Phattarachai\ClaudeTasksLaravel\Responses\QueuedTaskResponse;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;

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

    /**
     * Watch the run as it happens. Attaching a listener opts this run into the CLI's
     * event stream — `SomeTask::make()->onProgress(fn (ProgressEvent $e) => …)->run()`.
     *
     * @param  Closure(ProgressEvent): void  $callback
     */
    public function onProgress(Closure $callback): PendingRun
    {
        return new PendingRun($this, $callback);
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
