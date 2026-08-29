<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksManager;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Jobs\Concerns\InvokesQueuedResponseCallbacks;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;

class RunClaudeTask implements ShouldQueue
{
    use InvokesQueuedResponseCallbacks;
    use Queueable;

    public int $tries;

    /**
     * @var list<int>|int
     */
    public array|int $backoff;

    public ?int $timeout;

    public function __construct(public Task $task)
    {
        $this->tries = (int) config('claude-tasks.queue.tries', 3);
        $this->backoff = config('claude-tasks.queue.backoff', [60, 300, 900]);
        $this->timeout = config('claude-tasks.queue.timeout');

        $this->onConnection(config('claude-tasks.queue.connection'));
        $this->onQueue(config('claude-tasks.queue.queue'));
    }

    public function handle(ClaudeTasksManager $manager): void
    {
        $this->withCallbacks(fn (): TaskResponse => $manager->run($this->task));
    }

    public function displayName(): string
    {
        return $this->task::class;
    }
}
