<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;

/**
 * The argv for one headless run. `env -u` strips the nested-session guards
 * (belt) on top of the Process-level env removal (braces); --model appears only
 * when pinned by config or attribute; tools only when the Task declared them.
 * `$streaming` swaps the single JSON envelope for the newline-delimited event
 * stream (which the CLI only emits together with `--verbose`).
 */
final readonly class ClaudeCommand
{
    /**
     * @param  list<string>  $argv
     */
    private function __construct(public array $argv) {}

    public static function build(string $binary, string $prompt, TaskOptions $options, ?string $mcpConfigPath, bool $streaming = false): self
    {
        $argv = [
            'env', '-u', 'CLAUDECODE', '-u', 'AI_AGENT',
            $binary,
            '-p', $prompt,
            ...($streaming ? ['--output-format', 'stream-json', '--verbose'] : ['--output-format', 'json']),
            '--max-turns', (string) $options->maxTurns,
        ];

        if ($options->model !== null) {
            $argv = [...$argv, '--model', $options->model];
        }

        if ($options->allowedTools !== []) {
            $argv = [...$argv, '--allowedTools', ...$options->allowedTools];
        }

        if ($mcpConfigPath !== null) {
            $argv = [...$argv, '--mcp-config', $mcpConfigPath];
        }

        return new self($argv);
    }
}
