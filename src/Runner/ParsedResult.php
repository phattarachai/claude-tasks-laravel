<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;

final readonly class ParsedResult
{
    public function __construct(
        public string $text,
        public Usage $usage,
        public bool $isError = false,
    ) {}
}
