<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Schema;

use Illuminate\Contracts\Validation\Factory;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;
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
        return $this->split($task, $resultText)->data;
    }

    /**
     * Split the model's final message into the schema-validated JSON object and the prose it
     * wrote around that object. The object is located even when narration leaked in ahead of it
     * (see {@see jsonSource()}); the leftover text becomes {@see ParsedOutput::$narration}, so a
     * caller can show the explanation beside the data. Same failures as before — no object, or
     * one that misses the schema — still throw {@see InvalidTaskOutput}.
     */
    public function split(Task $task, string $resultText): ParsedOutput
    {
        $stripped = $this->stripFences($resultText);
        $source = $this->jsonSource($stripped);

        if ($source === null) {
            throw InvalidTaskOutput::notJson($resultText);
        }

        /** @var array<string, mixed> $data */
        $data = $this->asObject($source);

        $validator = $this->validator->make($data, SchemaRules::from(TaskSchema::serialize($task)));

        if ($validator->fails()) {
            throw InvalidTaskOutput::schemaMismatch($validator->errors()->toArray(), $resultText);
        }

        return new ParsedOutput($data, $this->narrationAround($stripped, $source));
    }

    /**
     * The gate for a {@see Format::Text} run:
     * the prose is returned verbatim (only surrounding whitespace trimmed), and an
     * empty result is the one failure — a text task must still produce something.
     */
    public function ensureText(string $resultText): string
    {
        $trimmed = trim($resultText);

        if ($trimmed === '') {
            throw InvalidTaskOutput::emptyText($resultText);
        }

        return $trimmed;
    }

    /**
     * The JSON substring of the (fence-stripped) text: the whole thing when it decodes to an
     * object as-is, else the last balanced `{...}` run carved out of any narration that leaked in
     * ahead of it. Null when there is no object at all. Returning the source *string* (not just the
     * decoded array) is what lets {@see narrationAround()} subtract it back out to recover the prose.
     */
    private function jsonSource(string $stripped): ?string
    {
        if ($this->asObject($stripped) !== null) {
            return $stripped;
        }

        $extracted = $this->extractLastObject($stripped);

        return $extracted !== null && $this->asObject($extracted) !== null ? $extracted : null;
    }

    /**
     * The prose left once the JSON object is removed from the text — the model's narration.
     * Empty when the message was a bare object. The object is spliced out by position (it is the
     * last one), so a value that repeats elsewhere in the prose is not touched.
     */
    private function narrationAround(string $stripped, string $source): string
    {
        if ($source === $stripped) {
            return '';
        }

        $pos = strrpos($stripped, $source);

        if ($pos === false) {
            return '';
        }

        $before = substr($stripped, 0, $pos);
        $after = substr($stripped, $pos + strlen($source));

        return trim(trim($before)."\n".trim($after));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function asObject(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Walk the text once and return the last top-level `{...}` run, counting braces
     * while skipping any that live inside a string literal (respecting `\"` escapes)
     * so prose punctuation or JSON string values never throw the balance off.
     */
    private function extractLastObject(string $text): ?string
    {
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;
        $candidate = null;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }

                $depth++;
            } elseif ($char === '}' && $depth > 0) {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $candidate = substr($text, $start, $i - $start + 1);
                    $start = null;
                }
            }
        }

        return $candidate;
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
