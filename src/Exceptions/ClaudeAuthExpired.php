<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Exceptions;

class ClaudeAuthExpired extends ClaudeTasksException
{
    public static function fromOutput(string $output): self
    {
        return new self("Claude Code needs a login — run `claude` then /login on this machine.\n\n".trim($output));
    }
}
