<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Events\TaskCompleted;
use Phattarachai\ClaudeTasksLaravel\Events\TaskFailed;
use Phattarachai\ClaudeTasksLaravel\Events\TaskStarting;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeAuthExpired;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Exceptions\InvalidTaskOutput;
use Phattarachai\ClaudeTasksLaravel\Facades\ClaudeTasks;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AttachmentTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\McpTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\ToolTask;

it('runs a task and returns schema-validated output with usage', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    $response = ClaudeTasks::run(new AnalyzeStatementTask);

    expect($response->output)->toBe(validStatementOutput())
        ->and($response['summary'])->toBe('January statement')
        ->and($response->json('categories.0.name'))->toBe('Food')
        ->and($response->usage->costUsd)->toBe(0.0421)
        ->and($response->usage->numTurns)->toBe(3)
        ->and($response->usage->durationMs)->toBe(4321)
        ->and($response->usage->sessionId)->toBe('sess-0123');
});

it('builds an argv with the nested-session guard, no model pin, and no tools by default', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = $process->command;

        return is_array($argv)
            && array_slice($argv, 0, 5) === ['env', '-u', 'CLAUDECODE', '-u', 'AI_AGENT']
            && $argv[5] === '/usr/local/bin/claude'
            && $argv[6] === '-p'
            && in_array('--output-format', $argv, true)
            && ! in_array('--model', $argv, true)
            && ! in_array('--allowedTools', $argv, true)
            && ! in_array('--mcp-config', $argv, true);
    });
});

it('pins --model when the config sets one', function (): void {
    config()->set('claude-tasks.model', 'claude-opus-5');
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = $process->command;

        return is_array($argv)
            && $argv[array_search('--model', $argv, true) + 1] === 'claude-opus-5';
    });
});

it('applies model, timeout, max-turns, and allowed-tools attributes', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(['ok' => true]))]);

    ClaudeTasks::run(new ToolTask);

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = $process->command;

        return is_array($argv)
            && $process->timeout === 60
            && $argv[array_search('--model', $argv, true) + 1] === 'claude-sonnet-5'
            && $argv[array_search('--max-turns', $argv, true) + 1] === '5'
            && in_array('Read', $argv, true)
            && in_array('mcp__laravel-boost__database-query', $argv, true);
    });
});

it('allows the Read tool automatically for attachments and lists them in the prompt', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(['summary' => 'ok']))]);

    ClaudeTasks::run(new AttachmentTask);

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = $process->command;

        return is_array($argv)
            && $argv[array_search('--allowedTools', $argv, true) + 1] === 'Read'
            && str_contains((string) $argv[7], '/tmp/statement.csv');
    });
});

it('writes a runtime mcp-config built from PHP_BINARY and base_path', function (): void {
    $captured = null;

    Process::fake(function (PendingProcess $process) use (&$captured) {
        $argv = (array) $process->command;
        $index = array_search('--mcp-config', $argv, true);
        $captured = $index === false ? null : json_decode((string) file_get_contents($argv[$index + 1]), true);

        return Process::result(claudeEnvelope(['rows' => 1]));
    });

    ClaudeTasks::run(new McpTask);

    expect($captured['mcpServers']['laravel-boost']['command'])->toBe(PHP_BINARY)
        ->and($captured['mcpServers']['laravel-boost']['args'])->toBe([base_path('artisan'), 'boost:mcp']);
});

it('strips markdown fences from the result text', function (): void {
    $fenced = "```json\n".json_encode(validStatementOutput())."\n```";

    Process::fake(['*' => Process::result(claudeEnvelope([], ['result' => $fenced]))]);

    expect(ClaudeTasks::run(new AnalyzeStatementTask)->output)->toBe(validStatementOutput());
});

it('throws ClaudeProcessFailed when the process exits non-zero', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    ClaudeTasks::run(new AnalyzeStatementTask);
})->throws(ClaudeProcessFailed::class);

it('throws ClaudeAuthExpired when the failure looks like a login problem', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'OAuth access token has expired. Please run /login', exitCode: 1)]);

    ClaudeTasks::run(new AnalyzeStatementTask);
})->throws(ClaudeAuthExpired::class);

it('throws ClaudeProcessFailed on an error result envelope', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope([], ['is_error' => true, 'subtype' => 'error_max_turns', 'result' => 'ran out of turns']))]);

    ClaudeTasks::run(new AnalyzeStatementTask);
})->throws(ClaudeProcessFailed::class);

it('throws InvalidTaskOutput with errors and raw output on a schema mismatch', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(['summary' => 'only a summary']))]);

    try {
        ClaudeTasks::run(new AnalyzeStatementTask);

        $this->fail('InvalidTaskOutput was not thrown.');
    } catch (InvalidTaskOutput $exception) {
        expect($exception->errors)->toHaveKeys(['total', 'confidence', 'categories'])
            ->and($exception->rawOutput)->toContain('only a summary');
    }
});

it('throws InvalidTaskOutput when the result is not JSON', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope([], ['result' => 'Here is my analysis in prose.']))]);

    ClaudeTasks::run(new AnalyzeStatementTask);
})->throws(InvalidTaskOutput::class);

it('dispatches TaskStarting and TaskCompleted on success', function (): void {
    Event::fake([TaskStarting::class, TaskCompleted::class, TaskFailed::class]);
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    ClaudeTasks::run(new AnalyzeStatementTask);

    Event::assertDispatched(TaskStarting::class);
    Event::assertDispatched(TaskCompleted::class, fn (TaskCompleted $event): bool => $event->response->output === validStatementOutput());
    Event::assertNotDispatched(TaskFailed::class);
});

it('dispatches TaskFailed on failure', function (): void {
    Event::fake([TaskFailed::class]);
    Process::fake(['*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

    expect(fn () => ClaudeTasks::run(new AnalyzeStatementTask))->toThrow(ClaudeProcessFailed::class);

    Event::assertDispatched(TaskFailed::class, fn (TaskFailed $event): bool => $event->exception instanceof ClaudeProcessFailed);
});

it('runs through the Runnable trait entry point', function (): void {
    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);

    expect(AnalyzeStatementTask::make(month: '2026-02')->run()->output)->toBe(validStatementOutput());
});
