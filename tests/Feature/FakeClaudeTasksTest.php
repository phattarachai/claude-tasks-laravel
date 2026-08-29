<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Streaming\AssistantText;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Streaming\ToolUseStarted;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\ToolTask;

it('returns schema-derived fake output without touching the CLI', function (): void {
    ClaudeTasks::fake();
    Process::fake();

    $response = ClaudeTasks::run(new AnalyzeStatementTask);

    expect($response->output)->toHaveKeys(['summary', 'total', 'confidence', 'categories'])
        ->and($response->json('confidence'))->toBeIn(['low', 'medium', 'high']);

    Process::assertNothingRan();
});

it('returns canned output for a task class', function (): void {
    ClaudeTasks::fake([
        ToolTask::class => ['ok' => true],
    ]);

    expect(ClaudeTasks::run(new ToolTask)->output)->toBe(['ok' => true]);
});

it('resolves closure outputs with the task instance', function (): void {
    ClaudeTasks::fake([
        AnalyzeStatementTask::class => fn (AnalyzeStatementTask $task): array => ['month' => $task->month],
    ]);

    expect(ClaudeTasks::run(new AnalyzeStatementTask('2026-03'))->output)->toBe(['month' => '2026-03']);
});

it('asserts ran with an optional truth test', function (): void {
    ClaudeTasks::fake();

    new AnalyzeStatementTask('2026-02')->run();

    ClaudeTasks::assertRan(AnalyzeStatementTask::class);
    ClaudeTasks::assertRan(AnalyzeStatementTask::class, fn (AnalyzeStatementTask $task): bool => $task->month === '2026-02');
    ClaudeTasks::assertRanTimes(AnalyzeStatementTask::class);
});

it('asserts queued tasks separately and counts them as ran', function (): void {
    ClaudeTasks::fake();

    new ToolTask()->queue();

    ClaudeTasks::assertQueued(ToolTask::class);
    ClaudeTasks::assertRan(ToolTask::class);
});

it('asserts nothing ran', function (): void {
    ClaudeTasks::fake();

    ClaudeTasks::assertNothingRan();
});

it('replays a faked progress sequence and closes it with the result', function (): void {
    ClaudeTasks::fake([ToolTask::class => ['ok' => true]])->withProgress(ToolTask::class, [
        new AssistantText('เจอ Tax ID'),
        new ToolUseStarted('mcp__laravel-boost__database-query', 'select * from partners'),
    ]);
    Process::fake();

    $seen = [];

    $response = ToolTask::make()
        ->onProgress(function (ProgressEvent $event) use (&$seen): void {
            $seen[] = $event;
        })
        ->run();

    expect($seen)->toHaveCount(3)
        ->and($seen[0]->text)->toBe('เจอ Tax ID')
        ->and($seen[1]->name)->toBe('mcp__laravel-boost__database-query')
        ->and($seen[2])->toBeInstanceOf(ResultReceived::class)
        ->and($seen[2]->text)->toBe($response->text)
        ->and($response->output)->toBe(['ok' => true]);

    ClaudeTasks::assertRan(ToolTask::class);
    Process::assertNothingRan();
});

it('takes the constructor shorthand and honours a caller-supplied result event', function (): void {
    ClaudeTasks::fake(
        [ToolTask::class => ['ok' => true]],
        [ToolTask::class => [new ResultReceived('{"ok":true}')]],
    );

    $seen = [];

    ToolTask::make()->onProgress(function (ProgressEvent $event) use (&$seen): void {
        $seen[] = $event;
    })->run();

    expect($seen)->toHaveCount(1)
        ->and($seen[0])->toBeInstanceOf(ResultReceived::class);
});

it('skips the progress replay entirely when no listener is attached', function (): void {
    ClaudeTasks::fake()->withProgress(ToolTask::class, [new AssistantText('never seen')]);

    expect(ToolTask::make()->run()->output)->toHaveKey('ok');
});
