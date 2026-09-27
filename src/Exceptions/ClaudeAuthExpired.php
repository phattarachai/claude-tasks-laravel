<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Exceptions;

use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeTokenStatus;

class ClaudeAuthExpired extends ClaudeTasksException
{
    public static function fromOutput(string $output): self
    {
        return new self("Claude Code needs a login — run `claude` then /login on this machine.\n\n".trim($output));
    }

    public static function fromTokenOutput(string $output, ClaudeTokenStatus $status, string $path): self
    {
        $expiry = $status->expiresAt?->toDateString();

        $state = match (true) {
            $status->isExpired() => "expired on {$expiry}",
            $expiry !== null => "was rejected (recorded expiry {$expiry})",
            default => 'was rejected',
        };

        return new self(
            "The Claude token at {$path} {$state} — run `claude setup-token` then `php artisan claude-tasks:token` on this machine.\n\n".trim($output),
        );
    }
}
