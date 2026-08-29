<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;

/**
 * Reads the Claude Code OAuth credentials file as a health tripwire — the CLI
 * refreshes tokens itself; this only answers "will the next run authenticate?".
 */
class ClaudeAuth
{
    /**
     * Substrings Claude Code prints when a run dies on authentication rather than on the task.
     */
    private const array FAILURE_MARKERS = [
        'OAuth access token has expired',
        'Failed to authenticate',
        'Please run /login',
        'Invalid API key',
        'invalid_api_key',
    ];

    public function status(): ClaudeAuthStatus
    {
        $credentials = $this->read();

        if ($credentials === null) {
            return ClaudeAuthStatus::missing();
        }

        return new ClaudeAuthStatus(
            credentialsFound: true,
            expiresAt: $this->expiryFrom($credentials),
            canRefresh: filled(Arr::get($credentials, 'claudeAiOauth.refreshToken')),
        );
    }

    public function looksLikeAuthFailure(string $output): bool
    {
        return Arr::first(
            self::FAILURE_MARKERS,
            fn (string $marker): bool => str_contains($output, $marker),
        ) !== null;
    }

    public function credentialsPath(): string
    {
        return (string) config('claude-tasks.credentials_path');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        $path = $this->credentialsPath();

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function expiryFrom(array $credentials): ?CarbonInterface
    {
        $milliseconds = Arr::get($credentials, 'claudeAiOauth.expiresAt');

        if (! is_numeric($milliseconds) || $milliseconds <= 0) {
            return null;
        }

        return Date::createFromTimestampMs((int) $milliseconds, config('app.timezone'));
    }
}
