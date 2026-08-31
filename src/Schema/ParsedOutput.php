<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Schema;

/**
 * The two halves of a model's final message once split: the schema-validated JSON
 * object the code consumes, and the prose the model wrote around it (empty when the
 * message was a bare object). See {@see OutputValidator::split()}.
 */
final readonly class ParsedOutput
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $narration,
    ) {}
}
