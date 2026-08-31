<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Exceptions;

class InvalidTaskOutput extends ClaudeTasksException
{
    /**
     * @var array<string, list<string>>
     */
    public array $errors = [];

    public string $rawOutput = '';

    public static function notJson(string $rawOutput): self
    {
        $exception = new self('Claude did not return a JSON object.');
        $exception->rawOutput = $rawOutput;

        return $exception;
    }

    public static function emptyText(string $rawOutput): self
    {
        $exception = new self('Claude returned an empty text response.');
        $exception->rawOutput = $rawOutput;

        return $exception;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function schemaMismatch(array $errors, string $rawOutput): self
    {
        $exception = new self('Claude output failed schema validation: '.json_encode($errors));
        $exception->errors = $errors;
        $exception->rawOutput = $rawOutput;

        return $exception;
    }
}
