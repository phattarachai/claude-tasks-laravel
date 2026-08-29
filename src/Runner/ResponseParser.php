<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;

/**
 * Parses the `--output-format json` envelope — a single result object on
 * current CLIs, an event list on older ones — into result text plus usage
 * (cost, turns, duration, session id).
 */
class ResponseParser
{
    public function parse(string $rawOutput): ParsedResult
    {
        $decoded = json_decode(trim($rawOutput), true);

        if (! is_array($decoded) || $decoded === []) {
            throw ClaudeProcessFailed::unparseableOutput($rawOutput);
        }

        if ($this->isSingleResult($decoded)) {
            return $this->fromSingleResult($decoded);
        }

        if (array_is_list($decoded)) {
            return $this->fromEventList($decoded, $rawOutput);
        }

        throw ClaudeProcessFailed::unparseableOutput($rawOutput);
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function isSingleResult(array $decoded): bool
    {
        return ($decoded['type'] ?? '') === 'result' && array_key_exists('result', $decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function fromSingleResult(array $decoded): ParsedResult
    {
        return new ParsedResult(
            text: (string) $decoded['result'],
            usage: $this->usageFrom($decoded, numTurns: $decoded['num_turns'] ?? null),
            isError: (bool) ($decoded['is_error'] ?? false) || ($decoded['subtype'] ?? 'success') !== 'success',
        );
    }

    /**
     * @param  list<mixed>  $events
     */
    private function fromEventList(array $events, string $rawOutput): ParsedResult
    {
        $assistantEvents = array_values(array_filter(
            $events,
            fn (mixed $event): bool => is_array($event) && ($event['type'] ?? '') === 'assistant',
        ));

        $resultEvent = collect($events)->last(
            fn (mixed $event): bool => is_array($event) && ($event['type'] ?? '') === 'result',
        );

        $text = is_array($resultEvent) && is_string($resultEvent['result'] ?? null)
            ? $resultEvent['result']
            : $this->lastAssistantText($assistantEvents);

        if ($text === null) {
            throw ClaudeProcessFailed::unparseableOutput($rawOutput);
        }

        return new ParsedResult(
            text: $text,
            usage: $this->usageFrom(is_array($resultEvent) ? $resultEvent : [], numTurns: count($assistantEvents)),
            isError: is_array($resultEvent) && ((bool) ($resultEvent['is_error'] ?? false)),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $assistantEvents
     */
    private function lastAssistantText(array $assistantEvents): ?string
    {
        $last = collect($assistantEvents)->last();

        if (! is_array($last)) {
            return null;
        }

        $text = collect($last['message']['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function usageFrom(array $envelope, mixed $numTurns): Usage
    {
        $cost = $envelope['total_cost_usd'] ?? $envelope['cost_usd'] ?? null;

        return new Usage(
            costUsd: is_numeric($cost) ? (float) $cost : null,
            numTurns: is_numeric($numTurns) ? (int) $numTurns : null,
            durationMs: is_numeric($envelope['duration_ms'] ?? null) ? (int) $envelope['duration_ms'] : null,
            sessionId: is_string($envelope['session_id'] ?? null) ? $envelope['session_id'] : null,
        );
    }
}
