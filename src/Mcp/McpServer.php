<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Mcp;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One stdio MCP server definition, built at runtime from the current machine's
 * PHP_BINARY and base_path() — never a hardcoded path from another machine.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class McpServer implements Arrayable
{
    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        public string $command,
        public array $args = [],
        public array $env = [],
    ) {}

    /**
     * The app's own Laravel Boost MCP server. Pair with a read-only tool allow
     * such as #[AllowedTools('mcp__laravel-boost__database-query')] — never tinker.
     */
    public static function laravelBoost(): self
    {
        return new self(PHP_BINARY, [base_path('artisan'), 'boost:mcp']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'command' => $this->command,
            'args' => $this->args,
            'env' => $this->env,
        ], fn (mixed $value): bool => $value !== []);
    }
}
