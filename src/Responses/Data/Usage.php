<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Responses\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class Usage implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?float $costUsd = null,
        public ?int $numTurns = null,
        public ?int $durationMs = null,
        public ?string $sessionId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cost_usd' => $this->costUsd,
            'num_turns' => $this->numTurns,
            'duration_ms' => $this->durationMs,
            'session_id' => $this->sessionId,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
