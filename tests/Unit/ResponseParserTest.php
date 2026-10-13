<?php

declare(strict_types=1);

use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Runner\ResponseParser;

it('parses a single result envelope with usage metadata', function (): void {
    $parsed = new ResponseParser()->parse(claudeEnvelope(['ok' => true]));

    expect($parsed->text)->toBe('{"ok":true}')
        ->and($parsed->isError)->toBeFalse()
        ->and($parsed->usage->costUsd)->toBe(0.0421)
        ->and($parsed->usage->numTurns)->toBe(3)
        ->and($parsed->usage->durationMs)->toBe(4321)
        ->and($parsed->usage->sessionId)->toBe('sess-0123');
});

it('parses the legacy cost_usd key', function (): void {
    $envelope = claudeEnvelope(['ok' => true]);
    $decoded = json_decode($envelope, true);
    unset($decoded['total_cost_usd']);
    $decoded['cost_usd'] = 0.01;

    $parsed = new ResponseParser()->parse((string) json_encode($decoded));

    expect($parsed->usage->costUsd)->toBe(0.01);
});

it('parses an event-list envelope', function (): void {
    $events = json_encode([
        ['type' => 'system', 'subtype' => 'init'],
        ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'working']]]],
        ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => '{"ok":true}']]]],
        ['type' => 'result', 'result' => '{"ok":true}', 'cost_usd' => 0.02, 'duration_ms' => 999, 'session_id' => 'sess-9'],
    ]);

    $parsed = new ResponseParser()->parse((string) $events);

    expect($parsed->text)->toBe('{"ok":true}')
        ->and($parsed->usage->costUsd)->toBe(0.02)
        ->and($parsed->usage->numTurns)->toBe(2)
        ->and($parsed->usage->durationMs)->toBe(999)
        ->and($parsed->usage->sessionId)->toBe('sess-9');
});

it('falls back to the last assistant text when the event list has no result event', function (): void {
    $events = json_encode([
        ['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => '{"ok":false}']]]],
    ]);

    expect(new ResponseParser()->parse((string) $events)->text)->toBe('{"ok":false}');
});

it('flags an error envelope', function (): void {
    $parsed = new ResponseParser()->parse(claudeEnvelope([], [
        'subtype' => 'error_max_turns',
        'is_error' => true,
        'result' => 'ran out of turns',
    ]));

    expect($parsed->isError)->toBeTrue();
});

it('throws on unparseable output', function (): void {
    new ResponseParser()->parse('not json at all');
})->throws(ClaudeProcessFailed::class);

it('reads token counts from modelUsage, summed across models', function (): void {
    $parsed = new ResponseParser()->parse(claudeEnvelope(['ok' => true], [
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        'modelUsage' => [
            'claude-opus-5-5' => [
                'inputTokens' => 120,
                'outputTokens' => 900,
                'cacheReadInputTokens' => 40000,
                'cacheCreationInputTokens' => 8000,
                'webSearchRequests' => 3,
                'costUSD' => 0.4,
            ],
            'claude-haiku-4-5' => [
                'inputTokens' => 30,
                'outputTokens' => 100,
                'cacheReadInputTokens' => 0,
                'cacheCreationInputTokens' => 500,
                'webSearchRequests' => 0,
                'costUSD' => 0.01,
            ],
        ],
    ]));

    expect($parsed->usage->inputTokens)->toBe(150)
        ->and($parsed->usage->outputTokens)->toBe(1000)
        ->and($parsed->usage->cacheReadTokens)->toBe(40000)
        ->and($parsed->usage->cacheWriteTokens)->toBe(8500)
        ->and($parsed->usage->webSearchRequests)->toBe(3)
        ->and($parsed->usage->models)->toBe(['claude-opus-5-5', 'claude-haiku-4-5'])
        ->and($parsed->usage->totalTokens())->toBe(49650);
});

it('falls back to the usage block when there is no modelUsage', function (): void {
    $parsed = new ResponseParser()->parse(claudeEnvelope(['ok' => true], [
        'usage' => [
            'input_tokens' => 10,
            'output_tokens' => 48,
            'cache_read_input_tokens' => 22383,
            'cache_creation_input_tokens' => 8457,
            'server_tool_use' => ['web_search_requests' => 2],
        ],
    ]));

    expect($parsed->usage->inputTokens)->toBe(10)
        ->and($parsed->usage->cacheReadTokens)->toBe(22383)
        ->and($parsed->usage->cacheWriteTokens)->toBe(8457)
        ->and($parsed->usage->webSearchRequests)->toBe(2)
        ->and($parsed->usage->models)->toBe([]);
});

it('leaves token counts null on an envelope without any', function (): void {
    $usage = new ResponseParser()->parse(claudeEnvelope(['ok' => true]))->usage;

    expect($usage->inputTokens)->toBeNull()
        ->and($usage->totalTokens())->toBeNull();
});
