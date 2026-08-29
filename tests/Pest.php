<?php

declare(strict_types=1);

use Phattarachai\ClaudeTasksLaravel\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * @param  array<string, mixed>  $output
 * @param  array<string, mixed>  $overrides
 */
function claudeEnvelope(array $output, array $overrides = []): string
{
    return (string) json_encode([
        'type' => 'result',
        'subtype' => 'success',
        'is_error' => false,
        'duration_ms' => 4321,
        'num_turns' => 3,
        'result' => json_encode($output),
        'session_id' => 'sess-0123',
        'total_cost_usd' => 0.0421,
        ...$overrides,
    ]);
}

/**
 * @return array<string, mixed>
 */
function validStatementOutput(): array
{
    return [
        'summary' => 'January statement',
        'total' => 49879.5,
        'confidence' => 'high',
        'categories' => [
            ['name' => 'Food', 'amount' => 120.5],
            ['name' => 'Income', 'amount' => 50000],
        ],
    ];
}
