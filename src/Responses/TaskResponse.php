<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Responses;

use ArrayAccess;
use LogicException;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;

/**
 * @implements ArrayAccess<string, mixed>
 */
final readonly class TaskResponse implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $output  schema-validated data — the caller writes the DB with it
     */
    public function __construct(
        public array $output,
        public Usage $usage,
        public string $text,
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
