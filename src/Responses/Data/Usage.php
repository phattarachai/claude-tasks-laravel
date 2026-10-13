<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Responses\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What one run spent, read off the CLI's result envelope. `costUsd` is the CLI's
 * API-list-price figure, not what a subscription bills; the token counts are what
 * a subscription's quota actually drains.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Usage implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $models
     */
    public function __construct(
        public ?float $costUsd = null,
        public ?int $numTurns = null,
        public ?int $durationMs = null,
        public ?string $sessionId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $cacheReadTokens = null,
        public ?int $cacheWriteTokens = null,
        public ?int $webSearchRequests = null,
        public array $models = [],
    ) {}

    /**
     * Every token the run moved — fresh input, cache reads and writes, and output.
     */
    public function totalTokens(): ?int
    {
        $counts = array_filter(
            [$this->inputTokens, $this->outputTokens, $this->cacheReadTokens, $this->cacheWriteTokens],
            fn (?int $count): bool => $count !== null,
        );

        return $counts === [] ? null : array_sum($counts);
    }

    /**
     * Two runs' usage summed — for a chain of calls, or a benchmark over several. The
     * session id is dropped, since a sum spans sessions.
     */
    public function plus(self $other): self
    {
        return new self(
            costUsd: self::sum($this->costUsd, $other->costUsd),
            numTurns: self::sum($this->numTurns, $other->numTurns),
            durationMs: self::sum($this->durationMs, $other->durationMs),
            inputTokens: self::sum($this->inputTokens, $other->inputTokens),
            outputTokens: self::sum($this->outputTokens, $other->outputTokens),
            cacheReadTokens: self::sum($this->cacheReadTokens, $other->cacheReadTokens),
            cacheWriteTokens: self::sum($this->cacheWriteTokens, $other->cacheWriteTokens),
            webSearchRequests: self::sum($this->webSearchRequests, $other->webSearchRequests),
            models: array_values(array_unique([...$this->models, ...$other->models])),
        );
    }

    /**
     * Rebuilds a Usage from its own `toArray()` — e.g. out of a stored `task_runs.options`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;

        return new self(
            costUsd: is_numeric($data['cost_usd'] ?? null) ? (float) $data['cost_usd'] : null,
            numTurns: $int('num_turns'),
            durationMs: $int('duration_ms'),
            sessionId: is_string($data['session_id'] ?? null) ? $data['session_id'] : null,
            inputTokens: $int('input_tokens'),
            outputTokens: $int('output_tokens'),
            cacheReadTokens: $int('cache_read_tokens'),
            cacheWriteTokens: $int('cache_write_tokens'),
            webSearchRequests: $int('web_search_requests'),
            models: array_values(array_filter((array) ($data['models'] ?? []), is_string(...))),
        );
    }

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
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'web_search_requests' => $this->webSearchRequests,
            'models' => $this->models,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @template T of int|float
     *
     * @param  T|null  $left
     * @param  T|null  $right
     * @return T|null
     */
    private static function sum(int|float|null $left, int|float|null $right): int|float|null
    {
        return $left === null && $right === null ? null : ($left ?? 0) + ($right ?? 0);
    }
}
