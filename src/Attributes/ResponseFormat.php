<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Attributes;

use Attribute;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;

/**
 * Pins a Task's response format at the call site — `#[ResponseFormat(Format::Text)]`
 * for a task that returns Markdown or plain prose instead of a JSON object. Absent,
 * the run defaults to {@see Format::Json} and enforces the declared `schema()`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ResponseFormat
{
    public function __construct(public Format $format = Format::Json) {}
}
