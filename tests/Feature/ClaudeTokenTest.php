<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeAuthExpired;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeProbe;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeToken;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeTokenStatus;
use Phattarachai\ClaudeTasksLaravel\Streaming\ProgressEvent;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;

function carriesToken(PendingProcess $process): bool
{
    return ($process->environment['CLAUDE_CODE_OAUTH_TOKEN'] ?? null) === 'sk-ant-oat01-secret-abcd'
        && $process->environment['CLAUDECODE'] === false
        && $process->environment['AI_AGENT'] === false;
}

it('passes the stored token to a normal run, a streaming run and the probe', function (): void {
    useValidToken();

    Process::fake(['*' => Process::sequence([
        Process::result(claudeEnvelope(validStatementOutput())),
        Process::describe()->output(streamLines()),
        Process::result(claudeEnvelope(['ok' => true])),
    ])]);

    AnalyzeStatementTask::make(month: '2026-02')->run();
    AnalyzeStatementTask::make(month: '2026-02')->onProgress(fn (ProgressEvent $event) => null)->run();
    expect(app(ClaudeProbe::class)->run()->passed)->toBeTrue();

    Process::assertRanTimes(carriesToken(...), 3);
    Process::assertRan(fn (PendingProcess $process): bool => in_array('stream-json', (array) $process->command, true) && carriesToken($process));
    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('sk-ant-oat01-secret-abcd', (array) $process->command, true));
});

it('still passes an expired token rather than falling back to the CLI login', function (): void {
    useValidToken(expiresInDays: -3);

    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);
    AnalyzeStatementTask::make(month: '2026-02')->run();

    Process::assertRan(carriesToken(...));
});

it('leaves the environment untouched when no token file exists', function (): void {
    useTokenFile();

    Process::fake(['*' => Process::result(claudeEnvelope(validStatementOutput()))]);
    AnalyzeStatementTask::make(month: '2026-02')->run();

    Process::assertRan(fn (PendingProcess $process): bool => $process->environment === ['CLAUDECODE' => false, 'AI_AGENT' => false]);
});

it('treats an absent, malformed or empty token file as not configured', function (): void {
    $cases = [
        'absent' => null,
        'not json' => '{nope',
        'no token key' => ['expires_at' => now()->addYear()->toIso8601String()],
        'blank token' => ['token' => '   '],
        'non-string token' => ['token' => ['sk-ant-oat01']],
    ];

    foreach ($cases as $case => $contents) {
        useTokenFile($contents);
        $token = app(ClaudeToken::class);

        expect($token->value())->toBeNull($case)
            ->and($token->status()->present)->toBeFalse($case);
    }
});

it('ignores an unparseable date without dropping the token', function (): void {
    useTokenFile(['token' => 'sk-ant-oat01-x', 'expires_at' => 'someday']);

    $status = app(ClaudeToken::class)->status();

    expect($status->present)->toBeTrue()
        ->and($status->expiresAt)->toBeNull()
        ->and($status->isExpired())->toBeFalse()
        ->and($status->daysLeft())->toBeNull();
});

it('round-trips a stored token into a 0600 file inside a 0700 directory', function (): void {
    Date::setTestNow('2026-09-27 10:00:00');
    $path = useTokenFile();
    $token = app(ClaudeToken::class);

    $token->store('sk-ant-oat01-new-wxyz', Date::parse('2027-09-27 10:00:00'));

    $status = $token->status();

    expect($token->value())->toBe('sk-ant-oat01-new-wxyz')
        ->and($status->issuedAt?->toDateTimeString())->toBe('2026-09-27 10:00:00')
        ->and($status->expiresAt?->toDateTimeString())->toBe('2027-09-27 10:00:00')
        ->and($status->daysLeft())->toBe(365)
        ->and(fileperms($path) & 0777)->toBe(0600)
        ->and(fileperms(dirname($path)) & 0777)->toBe(0700)
        ->and(glob(dirname($path).'/.token-*'))->toBe([]);
});

it('does the token status maths', function (): void {
    Date::setTestNow('2026-09-27 12:00:00');

    $in = fn (string $expiry): ClaudeTokenStatus => new ClaudeTokenStatus(true, null, Date::parse($expiry));

    expect($in('2026-10-07 18:00:00')->daysLeft())->toBe(10)
        ->and($in('2026-10-07 18:00:00')->expiresWithin(10))->toBeFalse()
        ->and($in('2026-10-07 18:00:00')->expiresWithin(11))->toBeTrue()
        ->and($in('2026-10-07 18:00:00')->isExpired())->toBeFalse()
        ->and($in('2026-09-27 11:00:00')->isExpired())->toBeTrue()
        ->and($in('2026-09-27 11:00:00')->daysLeft())->toBe(-1)
        ->and($in('2026-09-27 11:00:00')->expiresWithin(0))->toBeTrue()
        ->and(new ClaudeTokenStatus(true)->daysLeft())->toBeNull()
        ->and(new ClaudeTokenStatus(true)->expiresWithin(30))->toBeFalse()
        ->and(ClaudeTokenStatus::missing()->isExpired())->toBeFalse()
        ->and(ClaudeTokenStatus::missing()->toArray())->toBe([
            'present' => false, 'issued_at' => null, 'expires_at' => null, 'days_left' => null, 'expired' => false,
        ])
        ->and($in('2026-10-07 18:00:00')->toArray())->toMatchArray([
            'present' => true, 'expires_at' => Date::parse('2026-10-07 18:00:00')->toIso8601String(), 'days_left' => 10, 'expired' => false,
        ]);
});

it('blames the configured token, not /login, when a run dies on auth', function (): void {
    $path = useValidToken(expiresInDays: -3);

    Process::fake(['*' => Process::result(errorOutput: 'OAuth access token has expired. Please run /login', exitCode: 1)]);

    expect(fn () => AnalyzeStatementTask::make(month: '2026-02')->run())
        ->toThrow(ClaudeAuthExpired::class, "The Claude token at {$path} expired on");

    expect(fn () => AnalyzeStatementTask::make(month: '2026-02')->run())
        ->toThrow(ClaudeAuthExpired::class, 'claude setup-token');
});

it('keeps the /login message when no token is configured', function (): void {
    Process::fake(['*' => Process::result(errorOutput: 'OAuth access token has expired. Please run /login', exitCode: 1)]);

    expect(fn () => AnalyzeStatementTask::make(month: '2026-02')->run())
        ->toThrow(ClaudeAuthExpired::class, 'run `claude` then /login');
});
