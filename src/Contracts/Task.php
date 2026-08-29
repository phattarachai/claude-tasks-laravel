<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Contracts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Stringable;

/**
 * A declarative unit of headless Claude work: a prompt plus the JSON shape the
 * model must return. The model returns data; the app writes the database.
 */
interface Task
{
    public function instructions(): Stringable|string;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array;
}
