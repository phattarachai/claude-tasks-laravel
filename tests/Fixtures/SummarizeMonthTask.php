<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Override;
use Phattarachai\ClaudeTasksLaravel\Attributes\ResponseFormat;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;
use Stringable;

/**
 * A Format::Text task — the deliverable is Markdown prose, so it declares no schema.
 */
#[ResponseFormat(Format::Text)]
class SummarizeMonthTask implements Task
{
    use Runnable;

    public function __construct(public string $month = '2026-01') {}

    #[Override]
    public function instructions(): Stringable|string
    {
        return "Write a short Markdown summary of {$this->month}.";
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
