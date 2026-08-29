<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Schema;

use Illuminate\Contracts\Validation\Factory;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Exceptions\InvalidTaskOutput;
use Phattarachai\ClaudeTasksLaravel\Prompt\TaskSchema;

class OutputValidator
{
    public function __construct(private readonly Factory $validator) {}

    /**
     * @return array<string, mixed>
     */
    public function validate(Task $task, string $resultText): array
    {
        $decoded = json_decode($this->stripFences($resultText), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw InvalidTaskOutput::notJson($resultText);
        }

        $validator = $this->validator->make($decoded, SchemaRules::from(TaskSchema::serialize($task)));

        if ($validator->fails()) {
            throw InvalidTaskOutput::schemaMismatch($validator->errors()->toArray(), $resultText);
        }

        return $decoded;
    }

    private function stripFences(string $text): string
    {
        $trimmed = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }
}
