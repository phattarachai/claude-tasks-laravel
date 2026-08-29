<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Override;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasContext;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Stringable;

class AnalyzeStatementTask implements HasContext, Task
{
    use Runnable;

    public function __construct(public string $month = '2026-01') {}

    #[Override]
    public function instructions(): Stringable|string
    {
        return 'Categorize the bank statement lines for the month.';
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'total' => $schema->number()->required(),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'categories' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'amount' => $schema->number()->required(),
                ]),
            )->required(),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function context(): array
    {
        return [
            'Statement month' => $this->month,
            'Statement lines' => "01/01 COFFEE 120.00\n02/01 SALARY 50000.00",
        ];
    }
}
