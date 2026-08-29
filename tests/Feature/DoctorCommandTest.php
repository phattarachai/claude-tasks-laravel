<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

function writeCredentials(int $expiresInDays = 30): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'claude-credentials-');

    file_put_contents($path, json_encode([
        'claudeAiOauth' => [
            'accessToken' => 'sk-test',
            'refreshToken' => 'rf-test',
            'expiresAt' => now()->addDays($expiresInDays)->getTimestampMs(),
        ],
    ]));

    return $path;
}

it('reports a healthy binary and credentials', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake(['*' => Process::result('2.1.0 (Claude Code)')]);

    $this->artisan('claude-tasks:doctor')
        ->expectsOutputToContain('2.1.0 (Claude Code)')
        ->assertSuccessful();
});

it('fails when the credentials file is missing', function (): void {
    config()->set('claude-tasks.credentials_path', '/nonexistent/credentials.json');

    Process::fake(['*' => Process::result('2.1.0 (Claude Code)')]);

    $this->artisan('claude-tasks:doctor')->assertFailed();
});

it('fails when the token is expired with no refresh token', function (): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'claude-credentials-');

    file_put_contents($path, json_encode([
        'claudeAiOauth' => [
            'accessToken' => 'sk-test',
            'expiresAt' => now()->subDay()->getTimestampMs(),
        ],
    ]));

    config()->set('claude-tasks.credentials_path', $path);

    Process::fake(['*' => Process::result('2.1.0 (Claude Code)')]);

    $this->artisan('claude-tasks:doctor')->assertFailed();
});
