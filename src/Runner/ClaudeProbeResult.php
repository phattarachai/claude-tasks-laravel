<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Illuminate\Support\Str;

final readonly class ClaudeProbeResult
{
    private function __construct(
        public bool $passed,
        public string $detail,
        public bool $authFailure = false,
    ) {}

    public static function passed(string $text): self
    {
        return new self(passed: true, detail: Str::limit(trim($text), 120));
    }

    public static function failed(string $output, bool $authFailure): self
    {
        return new self(passed: false, detail: Str::limit(trim($output), 500), authFailure: $authFailure);
    }
}
