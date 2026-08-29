<?php

declare(strict_types=1);

use Phattarachai\ClaudeTasksLaravel\Streaming\AssistantText;
use Phattarachai\ClaudeTasksLaravel\Streaming\ResultReceived;
use Phattarachai\ClaudeTasksLaravel\Streaming\RunStarted;
use Phattarachai\ClaudeTasksLaravel\Streaming\StreamParser;
use Phattarachai\ClaudeTasksLaravel\Streaming\ToolUseStarted;

it('maps every modelled line type to its typed event, in order', function (): void {
    $events = new StreamParser()->push(implode('', array_map(
        fn (string $line): string => $line."\n",
        streamLines(),
    )));

    expect($events)->toHaveCount(4)
        ->and($events[0])->toBeInstanceOf(RunStarted::class)
        ->and($events[0]->sessionId)->toBe('sess-0123')
        ->and($events[0]->model)->toBe('claude-opus-5')
        ->and($events[0]->tools)->toBe(['Read'])
        ->and($events[1])->toBeInstanceOf(AssistantText::class)
        ->and($events[1]->text)->toBe('อ่านใบกำกับ')
        ->and($events[2])->toBeInstanceOf(ToolUseStarted::class)
        ->and($events[2]->name)->toBe('Read')
        ->and($events[2]->summary)->toBe('/tmp/invoice.jpg')
        ->and($events[2]->input)->toBe(['file_path' => '/tmp/invoice.jpg'])
        ->and($events[3])->toBeInstanceOf(ResultReceived::class)
        ->and($events[3]->text)->toBe(json_encode(validStatementOutput()))
        ->and($events[3]->usage->costUsd)->toBe(0.0421)
        ->and($events[3]->usage->numTurns)->toBe(3)
        ->and($events[3]->usage->sessionId)->toBe('sess-0123')
        ->and($events[3]->isError)->toBeFalse();
});

it('holds a partial line back until its newline arrives', function (): void {
    $parser = new StreamParser;
    $line = streamLines()[1];

    expect($parser->push(substr($line, 0, 20)))->toBe([])
        ->and($parser->push(substr($line, 20)))->toBe([]);

    $events = $parser->push("\n");

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AssistantText::class);
});

it('drains a trailing line the process never terminated', function (): void {
    $parser = new StreamParser;

    expect($parser->push(streamLines()[3]))->toBe([])
        ->and($parser->flush())->toHaveCount(1);
});

it('skips blank lines, non-JSON noise, and line types it does not model', function (): void {
    $events = new StreamParser()->push(implode("\n", [
        '',
        'Warning: something on stdout that is not JSON',
        '[1, 2, 3]',
        '{"type":"user","message":{"content":[{"type":"tool_result","content":"secret file body"}]}}',
        '{"type":"system","subtype":"compact_boundary"}',
        '',
    ])."\n");

    expect($events)->toBe([]);
});

it('emits one event per content block and drops empty text', function (): void {
    $events = new StreamParser()->push(json_encode([
        'type' => 'assistant',
        'message' => ['content' => [
            ['type' => 'text', 'text' => '   '],
            ['type' => 'text', 'text' => ' first '],
            ['type' => 'tool_use', 'name' => 'Bash', 'input' => ['command' => 'ls']],
            ['type' => 'thinking', 'thinking' => 'hidden'],
        ]],
    ])."\n");

    expect($events)->toHaveCount(2)
        ->and($events[0]->text)->toBe('first')
        ->and($events[1]->name)->toBe('Bash')
        ->and($events[1]->summary)->toBe('ls');
});

it('carries an error result through instead of throwing', function (): void {
    $events = new StreamParser()->push(json_encode([
        'type' => 'result',
        'subtype' => 'error_max_turns',
        'is_error' => true,
    ])."\n");

    expect($events[0])->toBeInstanceOf(ResultReceived::class)
        ->and($events[0]->isError)->toBeTrue()
        ->and($events[0]->text)->toBe('');
});

it('summarises tool input by the most telling argument, truncated', function (): void {
    expect(ToolUseStarted::summarize(['file_path' => '/tmp/a.jpg', 'offset' => 2]))->toBe('/tmp/a.jpg')
        ->and(ToolUseStarted::summarize(['thinking' => 'unnamed argument']))->toBe('unnamed argument')
        ->and(ToolUseStarted::summarize(['nested' => ['a']]))->toBe('')
        ->and(ToolUseStarted::summarize([]))->toBe('')
        ->and(ToolUseStarted::summarize(['query' => str_repeat('x', 300)]))->toHaveLength(123)
        ->and(ToolUseStarted::summarize(['query' => str_repeat('x', 300)]))->toEndWith('...');
});
