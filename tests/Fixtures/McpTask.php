<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Override;
use Phattarachai\ClaudeTasksLaravel\Attributes\AllowedTools;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Contracts\UsesMcpServers;
use Phattarachai\ClaudeTasksLaravel\Mcp\McpServer;
use Stringable;

#[AllowedTools('mcp__laravel-boost__database-query')]
class McpTask implements Task, UsesMcpServers
{
    use Runnable;

    #[Override]
    public function instructions(): Stringable|string
    {
        return 'Query the ledger read-only.';
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'rows' => $schema->integer()->required(),
        ];
    }

    /**
     * @return array<string, McpServer>
     */
    #[Override]
    public function mcpServers(): array
    {
        return [
            'laravel-boost' => McpServer::laravelBoost(),
        ];
    }
}
