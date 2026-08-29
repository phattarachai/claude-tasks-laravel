<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

/**
 * The CLI's `system`/`init` line — emitted once, before any turn.
 */
final readonly class RunStarted extends ProgressEvent
{
    /**
     * @param  list<string>  $tools
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $sessionId = null,
        public ?string $model = null,
        public array $tools = [],
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function fromLine(array $line): self
    {
        return new self(
            sessionId: is_string($line['session_id'] ?? null) ? $line['session_id'] : null,
            model: is_string($line['model'] ?? null) ? $line['model'] : null,
            tools: array_values(array_map(strval(...), array_filter(
                (array) ($line['tools'] ?? []),
                is_scalar(...),
            ))),
            raw: $line,
        );
    }
}
