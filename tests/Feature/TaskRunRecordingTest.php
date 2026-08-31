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
        ->and($run->options['model'])->toBeNull()
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

it('records the prompt and resolved parameters in the request column', function (): void {
    config()->set('claude-tasks.model', 'claude-opus-5');
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    $request = TaskRun::query()->sole()->request;

    expect($request['requested_model'])->toBe('claude-opus-5')
        ->and($request['output_format'])->toBe('json')
        ->and($request['response_format'])->toBe('json')
        ->and($request['max_turns'])->toBe(10)
        ->and($request['prompt'])->toContain('Categorize the bank statement');
});

it('logs the request even when the run fails before returning', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    expect(fn () => ClaudeTasks::run(new AnalyzeStatementTask))->toThrow(ClaudeProcessFailed::class);

    expect(TaskRun::query()->sole()->request['prompt'])->toContain('Categorize the bank statement');
});

it('omits the prompt from the request payload when log_prompt is off', function (): void {
    config()->set('claude-tasks.log_prompt', false);
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    $request = TaskRun::query()->sole()->request;

    expect($request)->not->toHaveKey('prompt')
        ->and($request['output_format'])->toBe('json')
        ->and($request['max_turns'])->toBe(10);
});

it('records nothing when the task_runs toggle is off', function (): void {
    config()->set('claude-tasks.task_runs.enabled', false);

    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    expect(TaskRun::query()->count())->toBe(0);
});
