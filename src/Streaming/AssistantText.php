<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

/**
 * A text block the model produced on its way to the answer — one event per block,
 * already trimmed. The final JSON payload does not arrive here; it rides
 * {@see ResultReceived}.
 */
final readonly class AssistantText extends ProgressEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(public string $text, array $raw = [])
    {
        parent::__construct($raw);
    }
}
