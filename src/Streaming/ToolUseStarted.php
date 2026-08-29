<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

use Illuminate\Support\Str;

/**
 * The model asked to run a tool. Only the request is surfaced — tool *results* stay
 * inside the CLI, so an activity feed shows "reading invoice.jpg", never file bodies.
 */
final readonly class ToolUseStarted extends ProgressEvent
{
    /**
     * Argument keys worth showing first, most specific wins.
     */
    private const array PREFERRED_KEYS = [
        'file_path', 'path', 'pattern', 'query', 'command', 'url', 'description', 'prompt',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public string $summary = '',
        public array $input = [],
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $raw
     */
    public static function fromBlock(array $block, array $raw = []): self
    {
        /** @var array<string, mixed> $input */
        $input = is_array($block['input'] ?? null) ? $block['input'] : [];

        return new self(
            name: is_string($block['name'] ?? null) ? $block['name'] : 'tool',
            summary: self::summarize($input),
            input: $input,
            raw: $raw,
        );
    }

    /**
     * A one-line, log-safe rendering of the call's arguments: the most telling scalar
     * argument, truncated — never the whole input, which can be a whole file.
     *
     * @param  array<string, mixed>  $input
     */
    public static function summarize(array $input): string
    {
        $scalars = array_filter($input, fn (mixed $value): bool => is_scalar($value) && (string) $value !== '');

        foreach (self::PREFERRED_KEYS as $key) {
            if (array_key_exists($key, $scalars)) {
                return Str::limit((string) $scalars[$key], 120);
            }
        }

        return $scalars === [] ? '' : Str::limit((string) reset($scalars), 120);
    }
}
