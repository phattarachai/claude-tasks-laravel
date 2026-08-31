<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Responses;

use ArrayAccess;
use LogicException;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;
use Phattarachai\ClaudeTasksLaravel\Support\RunManifest;

/**
 * @implements ArrayAccess<string, mixed>
 */
final readonly class TaskResponse implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $output  schema-validated data — the caller writes the DB with it
     * @param  string  $text  the model's raw final message, verbatim (prose + JSON, before any split)
     * @param  string  $narration  the prose the model wrote around the JSON, object removed — a human explanation of the output
     * @param  RunManifest|null  $request  what defined the call: the composed prompt + resolved parameters
     */
    public function __construct(
        public array $output,
        public Usage $usage,
        public string $text,
        public string $narration = '',
        public ?RunManifest $request = null,
    ) {}

    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->output : data_get($this->output, $key, $default);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->output);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->output[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Task responses are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Task responses are immutable.');
    }
}
