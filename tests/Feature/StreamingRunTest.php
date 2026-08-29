<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeAuthExpired;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Exceptions\InvalidTaskOutput;
use Phattarachai\ClaudeTasksLaravel\Streaming\AssistantText;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Streaming\RunStarted;
use Phattarachai\ClaudeTasksLaravel\Streaming\ToolUseStarted;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;

/**
 * @param  list<string>  $lines
 */
function fakeStream(array $lines, int $exitCode = 0): void
{
    Process::fake(['*' => Process::describe()->output($lines)->exitCode($exitCode)]);
}

it('asks the CLI for the event stream only when a listener is attached', function (): void {
    fakeStream(streamLines());

    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = (array) $process->command;

        return $argv[array_search('--output-format', $argv, true) + 1] === 'stream-json'
            && in_array('--verbose', $argv, true);
    });
});

it('keeps the plain JSON envelope when no listener is attached', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    AnalyzeStatementTask::make(month: '2026-02')->run();

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = (array) $process->command;

        return $argv[array_search('--output-format', $argv, true) + 1] === 'json'
            && ! in_array('--verbose', $argv, true);
    });
});

it('delivers events in stream order and still returns schema-validated output', function (): void {
    fakeStream(streamLines());

    $seen = [];

    $response = AnalyzeStatementTask::make(month: '2026-02')
        ->onProgress(function (ProgressEvent $event) use (&$seen): void {
            $seen[] = $event;
        })
        ->run();

    expect(array_map(fn (ProgressEvent $event): string => $event::class, $seen))->toBe([
        RunStarted::class,
        AssistantText::class,
        ToolUseStarted::class,
        ResultReceived::class,
    ]);

    expect($response->output)->toBe(validStatementOutput())
        ->and($response->usage->costUsd)->toBe(0.0421)
        ->and($response->usage->sessionId)->toBe('sess-0123');
});

it('keeps the events it already delivered when the run then dies', function (): void {
    // Events arrive while the process is being read, so a run that blows up half way
    // still leaves an activity trail — that is the whole point of streaming.
    Process::fake(['*' => Process::describe()
        ->output([streamLines()[0], streamLines()[1]])
        ->errorOutput('boom')
        ->exitCode(1)]);

    $seen = [];
    $listener = function (ProgressEvent $event) use (&$seen): void {
        $seen[] = $event;
    };

    expect(fn () => AnalyzeStatementTask::make(month: '2026-02')->onProgress($listener)->run())
        ->toThrow(ClaudeProcessFailed::class);

    expect($seen)->toHaveCount(2)
        ->and($seen[1])->toBeInstanceOf(AssistantText::class)
        ->and($seen[1]->text)->toBe('อ่านใบกำกับ');
});

it('applies the Task schema to the streamed result exactly as the sync path does', function (): void {
    fakeStream([
        streamLines()[0],
        claudeEnvelope(['summary' => 'only a summary']),
    ]);

    try {
        AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();

        $this->fail('InvalidTaskOutput was not thrown.');
    } catch (InvalidTaskOutput $exception) {
        expect($exception->errors)->toHaveKeys(['total', 'confidence', 'categories'])
            ->and($exception->rawOutput)->toContain('only a summary');
    }
});

it('throws ClaudeProcessFailed on an error result line in the stream', function (): void {
    fakeStream([
        streamLines()[0],
        claudeEnvelope([], ['is_error' => true, 'subtype' => 'error_max_turns', 'result' => 'ran out of turns']),
    ]);

    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();
})->throws(ClaudeProcessFailed::class);

it('throws ClaudeAuthExpired when a streamed run dies on login', function (): void {
    Process::fake(['*' => Process::describe()
        ->errorOutput('OAuth access token has expired. Please run /login')
        ->exitCode(1)]);

    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();
})->throws(ClaudeAuthExpired::class);

it('throws ClaudeProcessFailed when the stream ends without a result line', function (): void {
    fakeStream([streamLines()[0], streamLines()[1]]);

    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();
})->throws(ClaudeProcessFailed::class);

it('bounds a streamed run with the same timeout as a sync one', function (): void {
    fakeStream(streamLines());

    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();

    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === (int) config('claude-tasks.timeout'));
});
