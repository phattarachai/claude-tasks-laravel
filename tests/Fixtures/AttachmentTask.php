<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Override;
use Phattarachai\ClaudeTasksLaravel\Concerns\Runnable;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasAttachments;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Stringable;

class AttachmentTask implements HasAttachments, Task
{
    use Runnable;

    #[Override]
    public function instructions(): Stringable|string
    {
        return 'Summarize the attached statement.';
    }

    /**
     * @return array<string, Type>
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
        ];
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function attachments(): array
    {
        return ['/tmp/statement.csv'];
    }
}
