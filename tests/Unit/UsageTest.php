<?php

declare(strict_types=1);

use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;

it('sums two runs and drops the session id', function (): void {
    $first = new Usage(costUsd: 0.25, numTurns: 2, durationMs: 1000, sessionId: 'a', inputTokens: 10, outputTokens: 100, cacheReadTokens: 5000, webSearchRequests: 1, models: ['claude-opus-5-5']);
    $second = new Usage(costUsd: 0.5, numTurns: 3, durationMs: 2000, sessionId: 'b', inputTokens: 20, outputTokens: 200, cacheWriteTokens: 700, models: ['claude-opus-5-5', 'claude-haiku-4-5']);

    $sum = $first->plus($second);

    expect($sum->costUsd)->toBe(0.75)
        ->and($sum->numTurns)->toBe(5)
        ->and($sum->durationMs)->toBe(3000)
        ->and($sum->sessionId)->toBeNull()
        ->and($sum->inputTokens)->toBe(30)
        ->and($sum->outputTokens)->toBe(300)
        ->and($sum->cacheReadTokens)->toBe(5000)
        ->and($sum->cacheWriteTokens)->toBe(700)
        ->and($sum->webSearchRequests)->toBe(1)
        ->and($sum->models)->toBe(['claude-opus-5-5', 'claude-haiku-4-5']);
});

it('keeps a field null when neither side has it', function (): void {
    expect(new Usage()->plus(new Usage)->costUsd)->toBeNull();
});

it('round-trips through toArray', function (): void {
    $usage = new Usage(costUsd: 0.1, numTurns: 1, durationMs: 50, sessionId: 's', inputTokens: 1, outputTokens: 2, cacheReadTokens: 3, cacheWriteTokens: 4, webSearchRequests: 5, models: ['m']);

    expect(Usage::fromArray($usage->toArray()))->toEqual($usage);
});

it('rebuilds from a v0.4 options array with no token keys', function (): void {
    $usage = Usage::fromArray(['cost_usd' => 0.2, 'num_turns' => 4, 'task' => 'X']);

    expect($usage->costUsd)->toBe(0.2)
        ->and($usage->numTurns)->toBe(4)
        ->and($usage->inputTokens)->toBeNull()
        ->and($usage->models)->toBe([]);
});
