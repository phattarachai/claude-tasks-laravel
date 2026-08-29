<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Contracts;

use Phattarachai\ClaudeTasksLaravel\Mcp\McpServer;

interface UsesMcpServers
{
    /**
     * @return array<string, McpServer> server name => definition, serialized to a runtime --mcp-config file
     */
    public function mcpServers(): array;
}
