<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Attributes;

use Attribute;

/**
 * Explicit tool allowlist for a Task. Without this attribute a run gets NO tools —
 * headless mode denies every tool request that is not allowed up front.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AllowedTools
{
    /**
     * @var list<string>
     */
    public array $tools;

    public function __construct(string ...$tools)
    {
        $this->tools = array_values($tools);
    }
}
