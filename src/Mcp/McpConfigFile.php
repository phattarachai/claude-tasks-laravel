<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Mcp;

use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Contracts\UsesMcpServers;

class McpConfigFile
{
    public static function writeFor(Task $task): ?string
    {
        if (! $task instanceof UsesMcpServers || $task->mcpServers() === []) {
            return null;
        }

        $servers = collect($task->mcpServers())
            ->map(fn (McpServer $server): array => $server->toArray())
            ->all();

        $base = (string) tempnam(sys_get_temp_dir(), 'claude-tasks-mcp-');
        $path = $base.'.json';
        rename($base, $path);

        file_put_contents($path, json_encode(['mcpServers' => $servers], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    public static function cleanup(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
}
