<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class ClaudeTokenStatus
{
    public function __construct(
        public bool $present,
        public ?CarbonInterface $issuedAt = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    public static function missing(): self
    {
        return new self(present: false);
    }

    public function isExpired(): bool
    {
        return $this->present && $this->expiresAt?->isPast() === true;
    }

    public function daysLeft(): ?int
    {
        if (! $this->present || ! $this->expiresAt instanceof CarbonInterface) {
            return null;
        }

        return (int) floor(Date::now()->diffInDays($this->expiresAt, false));
    }

    public function expiresWithin(int $days): bool
    {
        return $this->present && $this->expiresAt?->lessThanOrEqualTo(Date::now()->addDays($days)) === true;
    }

    /**
     * @return array{present: bool, issued_at: ?string, expires_at: ?string, days_left: ?int, expired: bool}
     */
    public function toArray(): array
    {
        return [
            'present' => $this->present,
            'issued_at' => $this->issuedAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'days_left' => $this->daysLeft(),
            'expired' => $this->isExpired(),
        ];
    }
}
