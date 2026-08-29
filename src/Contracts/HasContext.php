<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Contracts;

interface HasContext
{
    /**
     * @return array<string, string> label => content, injected into the prompt as labeled sections
     */
    public function context(): array;
}
