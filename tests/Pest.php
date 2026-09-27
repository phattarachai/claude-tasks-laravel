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
 * The four `--output-format stream-json` lines of one healthy run, in the order the
 * CLI writes them: init, an assistant text turn, a tool call, the result envelope.
 *
 * @param  array<string, mixed>  $resultOverrides
 * @return list<string>
 */
function streamLines(array $resultOverrides = []): array
{
    return [
        (string) json_encode(['type' => 'system', 'subtype' => 'init', 'session_id' => 'sess-0123', 'model' => 'claude-opus-5', 'tools' => ['Read']]),
        (string) json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'อ่านใบกำกับ']]], 'session_id' => 'sess-0123']),
        (string) json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'Read', 'input' => ['file_path' => '/tmp/invoice.jpg']]]], 'session_id' => 'sess-0123']),
        claudeEnvelope(validStatementOutput(), $resultOverrides),
    ];
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

function probeEnvelope(string $result = 'ok'): string
{
    return (string) json_encode([
        'type' => 'result',
        'subtype' => 'success',
        'is_error' => false,
        'result' => $result,
    ]);
}

/**
 * Point `claude-tasks.token_path` at a fresh temp dir, write `$contents` there when given
 * (an array is JSON-encoded, a string written verbatim), and return the path.
 *
 * @param  array<string, mixed>|string|null  $contents
 */
function useTokenFile(array|string|null $contents = null): string
{
    $path = sys_get_temp_dir().'/claude-tasks-'.bin2hex(random_bytes(6)).'/token.json';

    config()->set('claude-tasks.token_path', $path);

    if ($contents === null) {
        return $path;
    }

    mkdir(dirname($path), 0700, true);
    file_put_contents($path, is_array($contents) ? (string) json_encode($contents) : $contents);

    return $path;
}

function useValidToken(int $expiresInDays = 200): string
{
    return useTokenFile([
        'token' => 'sk-ant-oat01-secret-abcd',
        'issued_at' => now()->subDays(10)->toIso8601String(),
        'expires_at' => now()->addDays($expiresInDays)->toIso8601String(),
    ]);
}
