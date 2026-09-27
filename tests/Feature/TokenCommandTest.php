<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeToken;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function tokenCommandTester(): CommandTester
{
    return new CommandTester(Artisan::all()['claude-tasks:token']);
}

it('stores a token piped on STDIN with a one-year expiry', function (): void {
    Date::setTestNow('2026-09-27 10:00:00');
    $path = useTokenFile();
    Process::fake();

    $tester = tokenCommandTester();
    $tester->setInputs(['  sk-ant-oat01-piped-9876  ']);

    expect($tester->execute(['--stdin' => true, '--no-probe' => true]))->toBe(Command::SUCCESS)
        ->and(app(ClaudeToken::class)->value())->toBe('sk-ant-oat01-piped-9876')
        ->and(app(ClaudeToken::class)->status()->expiresAt?->toDateString())->toBe('2027-09-27')
        ->and($tester->getDisplay())->toContain($path)->toContain('sk-ant-oat…9876')->not->toContain('piped');

    Process::assertNothingRan();
});

it('stores a token from the hidden prompt with an explicit --expires date', function (): void {
    useTokenFile();
    Process::fake();

    $this->artisan('claude-tasks:token', ['--expires' => '2027-06-30', '--no-probe' => true])
        ->expectsQuestion('Paste the token printed by `claude setup-token`', 'sk-ant-oat01-typed-1111')
        ->expectsOutputToContain('2027-06-30')
        ->assertSuccessful();

    expect(app(ClaudeToken::class)->status()->expiresAt?->toDateString())->toBe('2027-06-30');
});

it('rejects a value that is not a setup-token token or a malformed --expires, writing nothing', function (): void {
    $cases = [
        'wrong prefix' => ['sk-ant-api03-key', []],
        'bad expiry' => ['sk-ant-oat01-ok', ['--expires' => '2027-02-30']],
    ];

    foreach ($cases as $case => [$value, $options]) {
        $path = useTokenFile();

        $this->artisan('claude-tasks:token', [...$options, '--no-probe' => true])
            ->expectsQuestion('Paste the token printed by `claude setup-token`', $value)
            ->assertFailed();

        expect(file_exists($path))->toBeFalse($case);
    }
});

it('runs the live probe with the new token and fails when it is rejected', function (): void {
    useTokenFile();

    Process::fake(['*' => Process::sequence([
        Process::result(probeEnvelope()),
        Process::result(errorOutput: 'Invalid API key · Please run /login', exitCode: 1),
    ])]);

    $this->artisan('claude-tasks:token')
        ->expectsQuestion('Paste the token printed by `claude setup-token`', 'sk-ant-oat01-good-2222')
        ->expectsOutputToContain('passed — ok')
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => ($process->environment['CLAUDE_CODE_OAUTH_TOKEN'] ?? null) === 'sk-ant-oat01-good-2222');

    $this->artisan('claude-tasks:token')
        ->expectsQuestion('Paste the token printed by `claude setup-token`', 'sk-ant-oat01-bad-3333')
        ->expectsOutputToContain('authentication')
        ->assertFailed();
});

it('reports --status with an exit code that says whether runs can authenticate', function (): void {
    useTokenFile();
    $this->artisan('claude-tasks:token', ['--status' => true])
        ->expectsOutputToContain('not configured')
        ->assertFailed();

    useValidToken(expiresInDays: -1);
    $this->artisan('claude-tasks:token', ['--status' => true])
        ->expectsOutputToContain('expired')
        ->assertFailed();

    useValidToken(expiresInDays: 100);
    $this->artisan('claude-tasks:token', ['--status' => true])
        ->expectsOutputToContain('sk-ant-oat…abcd')
        ->doesntExpectOutputToContain('secret')
        ->assertSuccessful();
});
