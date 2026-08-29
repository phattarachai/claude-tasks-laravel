<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Timeout
{
    public function __construct(public int $value) {}
}
