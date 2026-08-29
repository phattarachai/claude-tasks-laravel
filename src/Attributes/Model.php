<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Model
{
    public function __construct(public string $value) {}
}
