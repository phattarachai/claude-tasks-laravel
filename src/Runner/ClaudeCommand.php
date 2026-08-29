<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;

/**
 * The argv for one headless run. `env -u` strips the nested-session guards
 * (belt) on top of the Process-level env removal (braces); the model is always
 * pinned; tools appear only when the Task declared them.
 */
final readonly class ClaudeCommand
{
    /**
     * @param  list<string>  $argv
     */
    private function __construct(public array $argv) {}

    public static function build(string $binary, string $prompt, TaskOptions $options, ?string $mcpConfigPath): self
    {
        $argv = [
            'env', '-u', 'CLAUDECODE', '-u', 'AI_AGENT',
            $binary,
            '-p', $prompt,
            '--output-format', 'json',
            '--model', $options->model,
            '--max-turns', (string) $options->maxTurns,
        ];

        if ($options->allowedTools !== []) {
            $argv = [...$argv, '--allowedTools', ...$options->allowedTools];
        }

        if ($mcpConfigPath !== null) {
            $argv = [...$argv, '--mcp-config', $mcpConfigPath];
        }

        return new self($argv);
    }
}
