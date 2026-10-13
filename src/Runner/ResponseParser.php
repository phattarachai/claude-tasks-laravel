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
     * One `result` line out of a `--output-format stream-json` run — the same envelope
     * the synchronous path receives on its own, minus the surrounding decode.
     *
     * @param  array<string, mixed>  $line
     */
    public function parseResultLine(array $line): ParsedResult
    {
        return $this->fromSingleResult($line);
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
            text: (string) ($decoded['result'] ?? ''),
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
        $tokens = $this->tokensFrom($envelope);

        return new Usage(
            costUsd: is_numeric($cost) ? (float) $cost : null,
            numTurns: is_numeric($numTurns) ? (int) $numTurns : null,
            durationMs: is_numeric($envelope['duration_ms'] ?? null) ? (int) $envelope['duration_ms'] : null,
            sessionId: is_string($envelope['session_id'] ?? null) ? $envelope['session_id'] : null,
            inputTokens: $tokens['input'],
            outputTokens: $tokens['output'],
            cacheReadTokens: $tokens['cache_read'],
            cacheWriteTokens: $tokens['cache_write'],
            webSearchRequests: $tokens['web_search'],
            models: $tokens['models'],
        );
    }

    /**
     * Token counts, preferring `modelUsage` — per model, so it also counts a helper
     * model the CLI ran beside the main one — over the main model's `usage` block.
     *
     * @param  array<string, mixed>  $envelope
     * @return array{input: ?int, output: ?int, cache_read: ?int, cache_write: ?int, web_search: ?int, models: list<string>}
     */
    private function tokensFrom(array $envelope): array
    {
        $perModel = array_filter((array) ($envelope['modelUsage'] ?? []), is_array(...));

        if ($perModel !== []) {
            $sum = fn (string $key): ?int => $this->sumOf(array_column($perModel, $key));

            return [
                'input' => $sum('inputTokens'),
                'output' => $sum('outputTokens'),
                'cache_read' => $sum('cacheReadInputTokens'),
                'cache_write' => $sum('cacheCreationInputTokens'),
                'web_search' => $sum('webSearchRequests'),
                'models' => array_map(strval(...), array_keys($perModel)),
            ];
        }

        $usage = (array) ($envelope['usage'] ?? []);
        $count = fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null;

        return [
            'input' => $count($usage['input_tokens'] ?? null),
            'output' => $count($usage['output_tokens'] ?? null),
            'cache_read' => $count($usage['cache_read_input_tokens'] ?? null),
            'cache_write' => $count($usage['cache_creation_input_tokens'] ?? null),
            'web_search' => $count($usage['server_tool_use']['web_search_requests'] ?? null),
            'models' => [],
        ];
    }

    /**
     * @param  array<mixed>  $values
     */
    private function sumOf(array $values): ?int
    {
        $numbers = array_filter($values, is_numeric(...));

        return $numbers === [] ? null : (int) array_sum($numbers);
    }
}
