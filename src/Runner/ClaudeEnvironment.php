<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

/**
 * The environment every spawned `claude` process gets: the nested-session guards
 * removed, plus the stored OAuth token when one is configured — passed even when
 * expired, so a stale token fails loudly instead of falling back to the Keychain.
 */
class ClaudeEnvironment
{
    public function __construct(private readonly ClaudeToken $token) {}

    /**
     * @return array<string, string|false>
     */
    public function variables(): array
    {
        $token = $this->token->value();

        return [
            'CLAUDECODE' => false,
            'AI_AGENT' => false,
            ...($token === null ? [] : ['CLAUDE_CODE_OAUTH_TOKEN' => $token]),
        ];
    }
}
