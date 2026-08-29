<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Carbon\CarbonInterface;

final readonly class ClaudeAuthStatus
{
    public function __construct(
        public bool $credentialsFound,
        public ?CarbonInterface $expiresAt = null,
        public bool $canRefresh = false,
    ) {}

    public static function missing(): self
    {
        return new self(credentialsFound: false);
    }

    public function isExpired(): bool
    {
        return $this->expiresAt?->isPast() ?? false;
    }

    public function isHealthy(): bool
    {
        return $this->credentialsFound && (! $this->isExpired() || $this->canRefresh);
    }
}
