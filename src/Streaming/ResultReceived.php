<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;

/**
 * The CLI's closing `result` line — always the last event of a run. `$text` is the
 * same payload the synchronous path validates against the Task's schema, so it is
 * *not* yet validated here: read the returned `TaskResponse` for that.
 */
final readonly class ResultReceived extends ProgressEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $text,
        public Usage $usage = new Usage,
        public bool $isError = false,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }
}
