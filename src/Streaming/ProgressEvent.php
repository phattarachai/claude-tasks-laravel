<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

/**
 * One decoded line of `--output-format stream-json`, handed to the `onProgress`
 * listener the moment it arrives. `$raw` keeps the undecorated envelope so a
 * consumer can reach anything the typed subclasses deliberately don't surface.
 */
abstract readonly class ProgressEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(public array $raw = []) {}
}
