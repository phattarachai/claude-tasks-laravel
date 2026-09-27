<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Throwable;

/**
 * The machine's long-lived `claude setup-token` OAuth token, kept in one JSON file
 * with the expiry recorded at store time (the token itself is opaque). Read fresh
 * on every call, so a rotated token takes effect without restarting workers.
 */
class ClaudeToken
{
    public function path(): string
    {
        return (string) config('claude-tasks.token_path');
    }

    public function value(): ?string
    {
        return $this->read()['token'] ?? null;
    }

    public function status(): ClaudeTokenStatus
    {
        $stored = $this->read();

        if ($stored === null) {
            return ClaudeTokenStatus::missing();
        }

        return new ClaudeTokenStatus(
            present: true,
            issuedAt: $this->date($stored['issued_at']),
            expiresAt: $this->date($stored['expires_at']),
        );
    }

    public function store(string $token, CarbonInterface $expiresAt): void
    {
        $path = $this->path();
        $directory = dirname($path);

        $this->ensureDirectory($directory);

        $temporary = tempnam($directory, '.token-');

        if ($temporary === false) {
            throw new RuntimeException("Cannot write the Claude token into {$directory}.");
        }

        file_put_contents($temporary, json_encode([
            'token' => $token,
            'issued_at' => Date::now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        chmod($temporary, 0600);
        rename($temporary, $path);
    }

    /**
     * @return array{token: string, issued_at: mixed, expires_at: mixed}|null
     */
    private function read(): ?array
    {
        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        $token = is_array($decoded) ? ($decoded['token'] ?? null) : null;

        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return [
            'token' => trim($token),
            'issued_at' => $decoded['issued_at'] ?? null,
            'expires_at' => $decoded['expires_at'] ?? null,
        ];
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Date::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory} for the Claude token.");
        }
    }
}
