<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Override;
use Phattarachai\ClaudeTasksLaravel\Attributes\AllowedTools;
use Phattarachai\ClaudeTasksLaravel\Attributes\MaxTurns;
use Phattarachai\ClaudeTasksLaravel\Attributes\Model;
use Phattarachai\ClaudeTasksLaravel\Attributes\Timeout;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Stringable;

#[Model('claude-sonnet-5')]
#[Timeout(60)]
#[MaxTurns(5)]
#[AllowedTools('Read', 'mcp__laravel-boost__database-query')]
class ToolTask implements Task
{
    use Runnable;

    #[Override]
    public function instructions(): Stringable|string
    {
        return 'Check the ledger.';
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'ok' => $schema->boolean()->required(),
        ];
    }
}
