<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Exceptions;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Str;

class ClaudeProcessFailed extends ClaudeTasksException
{
    public ?int $exitCode = null;

    public string $rawOutput = '';

    public static function fromResult(ProcessResult $result): self
    {
        $error = trim($result->errorOutput() ?: $result->output()) ?: 'Claude exited '.$result->exitCode();

        $exception = new self('Claude process failed: '.Str::limit($error, 500));
        $exception->exitCode = $result->exitCode();
        $exception->rawOutput = $result->output();

        return $exception;
    }

    public static function unparseableOutput(string $rawOutput): self
    {
        $exception = new self('Claude produced output that is not a recognizable JSON envelope.');
        $exception->rawOutput = $rawOutput;

        return $exception;
    }

    public static function errorResult(string $text): self
    {
        $exception = new self('Claude reported an error result: '.Str::limit(trim($text), 500));
        $exception->rawOutput = $text;

        return $exception;
    }
}
