<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksManager;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Jobs\RunClaudeTask;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;

it('queues a task as a RunClaudeTask job', function (): void {
    Queue::fake();

    ClaudeTasks::queue(new AnalyzeStatementTask);

    Queue::assertPushed(RunClaudeTask::class, fn (RunClaudeTask $job): bool => $job->task instanceof AnalyzeStatementTask);
});

it('queues through the Runnable trait with config tries and backoff', function (): void {
    Queue::fake();

    config()->set('claude-tasks.queue.queue', 'ai');

    AnalyzeStatementTask::make()->queue();

    Queue::assertPushedOn('ai', RunClaudeTask::class, fn (RunClaudeTask $job): bool => $job->tries === 3 && $job->backoff === [60, 300, 900]);
});

it('invokes then callbacks with the response when the job runs', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    $received = null;

    $job = new RunClaudeTask(new AnalyzeStatementTask);
    $job->then(function (TaskResponse $response) use (&$received): void {
        $received = $response->output;
    });

    $job->handle(app(ClaudeTasksManager::class));

    expect($received)->toBe(validStatementOutput());
});

it('invokes catch callbacks when the job fails', function (): void {
    $caught = null;

    $job = new RunClaudeTask(new AnalyzeStatementTask);
    $job->catch(function (Throwable $exception) use (&$caught): void {
        $caught = $exception;
    });

    $job->failed(ClaudeProcessFailed::unparseableOutput('garbage'));

    expect($caught)->toBeInstanceOf(ClaudeProcessFailed::class);
});
