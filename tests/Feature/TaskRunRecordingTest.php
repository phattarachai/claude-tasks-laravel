<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;
use Phattarachai\TaskRunsLaravel\Models\TaskRun;

it('records a successful run as a task_runs row with usage in options', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    $run = TaskRun::query()->sole();

    expect($run->type)->toBe('analyze-statement-task')
        ->and($run->status)->toBe(TaskRun::SUCCESS)
        ->and($run->dispatched_by)->toBe('claude-tasks')
        ->and($run->options['task'])->toBe(AnalyzeStatementTask::class)
        ->and($run->options['model'])->toBe('claude-opus-5')
        ->and($run->options['cost_usd'])->toBe(0.0421)
        ->and($run->options['num_turns'])->toBe(3)
        ->and($run->options['session_id'])->toBe('sess-0123')
        ->and($run->started_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull();
});

it('marks the task_runs row failed when the run throws', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    expect(fn () => ClaudeTasks::run(new AnalyzeStatementTask))->toThrow(ClaudeProcessFailed::class);

    $run = TaskRun::query()->sole();

    expect($run->status)->toBe(TaskRun::FAILED)
        ->and($run->message)->toContain('boom');
});

it('records nothing when the task_runs toggle is off', function (): void {
    config()->set('claude-tasks.task_runs.enabled', false);

    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    expect(TaskRun::query()->count())->toBe(0);
});
