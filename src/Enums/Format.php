<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Enums;

use Phattarachai\ClaudeTasksLaravel\Attributes\ResponseFormat;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use ReflectionClass;

/**
 * The shape a Task's final message must take. `Json` (the default) enforces the
 * declared `schema()`; `Text` accepts free Markdown or plain prose verbatim and
 * skips the schema gate — the deliverable is `TaskResponse::$text`.
 */
enum Format
{
    case Json;
    case Text;

    /**
     * Read the Task's `#[ResponseFormat]` attribute, defaulting to JSON when none
     * is declared. This is the single place the attribute is resolved.
     */
    public static function for(Task $task): self
    {
        $attributes = new ReflectionClass($task)->getAttributes(ResponseFormat::class);

        return $attributes === [] ? self::Json : $attributes[0]->newInstance()->format;
    }
}
