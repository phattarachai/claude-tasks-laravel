<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
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

function probeEnvelope(string $result = 'ok'): string
{
    return (string) json_encode([
        'type' => 'result',
        'subtype' => 'success',
        'is_error' => false,
        'result' => $result,
    ]);
}

it('reports a healthy binary and credentials, noting the probe is opt-in', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake(['*' => Process::result('2.1.0 (Claude Code)')]);

    $this->artisan('claude-tasks:doctor')
        ->expectsOutputToContain('2.1.0 (Claude Code)')
        ->expectsOutputToContain('pass --probe')
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

it('passes the live probe when a one-turn headless run succeeds', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake([
        '*--version*' => Process::result('2.1.0 (Claude Code)'),
        '*find-generic-password*' => Process::result(exitCode: 1),
        '*' => Process::result(probeEnvelope()),
    ]);

    $this->artisan('claude-tasks:doctor', ['--probe' => true])
        ->expectsOutputToContain('passed — ok')
        ->assertSuccessful();

    Process::assertRan(function (PendingProcess $process): bool {
        $argv = (array) $process->command;

        return in_array('--max-turns', $argv, true)
            && in_array('1', $argv, true)
            && ! in_array('--allowedTools', $argv, true);
    });
});

it('fails the live probe with the failure classified as authentication', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake([
        '*--version*' => Process::result('2.1.0 (Claude Code)'),
        '*find-generic-password*' => Process::result(exitCode: 1),
        '*' => Process::result(
            errorOutput: 'Failed to authenticate: OAuth session expired and could not be refreshed',
            exitCode: 1,
        ),
    ]);

    $this->artisan('claude-tasks:doctor', ['--probe' => true])
        ->expectsOutputToContain('authentication')
        ->assertFailed();
});

it('fails the live probe when the CLI reports an error result', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake([
        '*--version*' => Process::result('2.1.0 (Claude Code)'),
        '*find-generic-password*' => Process::result(exitCode: 1),
        '*' => Process::result((string) json_encode([
            'type' => 'result',
            'subtype' => 'error_max_turns',
            'is_error' => true,
            'result' => 'ran out of turns',
        ])),
    ]);

    $this->artisan('claude-tasks:doctor', ['--probe' => true])
        ->expectsOutputToContain('process')
        ->assertFailed();
});

it('warns when a Claude Code Keychain item exists alongside the credentials file', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake([
        '*--version*' => Process::result('2.1.0 (Claude Code)'),
        '*find-generic-password*' => Process::result('keychain: "login.keychain-db"'),
        '*' => Process::result(probeEnvelope()),
    ]);

    $this->artisan('claude-tasks:doctor')
        ->expectsOutputToContain('Keychain item')
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'security', 'find-generic-password', '-s', 'Claude Code-credentials',
    ]);
})->skip(PHP_OS_FAMILY !== 'Darwin', 'the Keychain check only runs on macOS');

it('stays quiet about the Keychain when no item exists', function (): void {
    config()->set('claude-tasks.credentials_path', writeCredentials());

    Process::fake([
        '*--version*' => Process::result('2.1.0 (Claude Code)'),
        '*find-generic-password*' => Process::result(errorOutput: 'The specified item could not be found in the keychain.', exitCode: 44),
        '*' => Process::result(probeEnvelope()),
    ]);

    $this->artisan('claude-tasks:doctor')
        ->doesntExpectOutputToContain('Keychain item')
        ->assertSuccessful();
})->skip(PHP_OS_FAMILY !== 'Darwin', 'the Keychain check only runs on macOS');
