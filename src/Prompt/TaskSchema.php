<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Prompt;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;

class TaskSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(Task $task): array
    {
        $factory = new JsonSchemaTypeFactory;

        return $factory->object($task->schema($factory))
            ->withoutAdditionalProperties()
            ->toArray();
    }
}
