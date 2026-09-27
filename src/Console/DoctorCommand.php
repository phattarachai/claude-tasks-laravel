<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeAuth;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeBinary;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeProbe;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeToken;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'claude-tasks:doctor', description: 'Check the Claude Code binary, version, and OAuth credential health')]
class DoctorCommand extends Command
{
    /**
     * The Keychain service name the Claude Code CLI prefers over the credentials file on macOS.
     */
    private const string KEYCHAIN_SERVICE = 'Claude Code-credentials';

    public function handle(ClaudeBinary $binary, ClaudeAuth $auth, ClaudeToken $token, ClaudeProbe $probe): int
    {
        $tokenConfigured = $token->status()->present;

        $binaryHealthy = $this->checkBinary($binary);
        $tokenHealthy = $this->checkToken($token);
        $authHealthy = $tokenConfigured ? $this->describeAuth($auth) : $this->checkAuth($auth);

        $this->warnWhenKeychainCanDiverge($tokenConfigured);

        $probeHealthy = $binaryHealthy ? $this->checkProbe($probe, $tokenConfigured) : false;

        $this->line('');
        $this->components->twoColumnDetail('Pinned model', (string) config('claude-tasks.model'));

        return $binaryHealthy && $tokenHealthy && $authHealthy && $probeHealthy ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{0: string, 1: null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['probe', null, InputOption::VALUE_NONE, 'Run a real one-turn headless call through the same path queued tasks use'],
        ];
    }

    private function checkBinary(ClaudeBinary $binary): bool
    {
        $located = $binary->locate();

        if ($located === null && trim((string) config('claude-tasks.binary')) === '') {
            $this->components->error('Claude binary not found on PATH or in the common install directories.');

            return false;
        }

        $path = $binary->path();

        $this->components->twoColumnDetail('Binary', $path);
        $this->components->twoColumnDetail('Version', $this->version($path));

        return true;
    }

    private function version(string $path): string
    {
        $result = Process::timeout(30)->run([$path, '--version']);

        return $result->successful() ? trim($result->output()) : 'unknown ('.trim($result->errorOutput()).')';
    }

    private function checkToken(ClaudeToken $token): bool
    {
        $status = $token->status();

        if (! $status->present) {
            $this->components->twoColumnDetail('Token', 'not configured');

            return true;
        }

        $this->components->twoColumnDetail('Token', $token->path());
        $this->components->twoColumnDetail('Token expires', $status->expiresAt?->toDateString() ?? 'unknown');

        if ($status->isExpired()) {
            $this->components->error('The Claude token has expired — run `claude setup-token` then `php artisan claude-tasks:token`.');

            return false;
        }

        $this->components->twoColumnDetail('Token days left', (string) ($status->daysLeft() ?? 'unknown'));

        if ($status->expiresWithin(30)) {
            $this->components->warn('The Claude token expires within 30 days — rotate it with `claude setup-token` then `php artisan claude-tasks:token`.');
        }

        return true;
    }

    /**
     * With a stored token every run authenticates through CLAUDE_CODE_OAUTH_TOKEN,
     * so the CLI login is reported for information only and never fails the check.
     */
    private function describeAuth(ClaudeAuth $auth): bool
    {
        $status = $auth->status();

        if (! $status->credentialsFound) {
            $this->components->twoColumnDetail('Credentials', 'none — not needed, the token is used');

            return true;
        }

        $this->components->twoColumnDetail('Credentials', $auth->credentialsPath().' (unused — the token is used)');
        $this->components->twoColumnDetail('Login expires', $status->expiresAt?->toDateTimeString() ?? 'unknown');

        return true;
    }

    private function checkAuth(ClaudeAuth $auth): bool
    {
        $status = $auth->status();

        if (! $status->credentialsFound) {
            $this->components->error("No Claude credentials at {$auth->credentialsPath()} — run `claude` then /login.");

            return false;
        }

        $this->components->twoColumnDetail('Credentials', $auth->credentialsPath());
        $this->components->twoColumnDetail('Token expires', $status->expiresAt?->toDateTimeString() ?? 'unknown');
        $this->components->twoColumnDetail('Refresh token', $status->canRefresh ? 'present' : 'missing');

        if (! $status->isHealthy()) {
            $this->components->error('Claude OAuth token is expired with no refresh token — run /login again.');

            return false;
        }

        return true;
    }

    /**
     * The CLI prefers the Keychain item over the credentials file, but launchd/GUI
     * and ssh contexts can resolve different keychains — so the file this command
     * inspects may not be the token queued workers actually authenticate with.
     */
    private function warnWhenKeychainCanDiverge(bool $tokenConfigured): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return;
        }

        $result = Process::timeout(10)->run(['security', 'find-generic-password', '-s', self::KEYCHAIN_SERVICE]);

        if ($result->failed()) {
            return;
        }

        if ($tokenConfigured) {
            $this->components->twoColumnDetail('Keychain item', '"'.self::KEYCHAIN_SERVICE.'" present — ignored, the token overrides it');

            return;
        }

        $this->components->twoColumnDetail('Keychain item', '"'.self::KEYCHAIN_SERVICE.'" present in the login keychain');
        $this->components->warn(
            'The Claude CLI prefers this Keychain item over the credentials file, but GUI/launchd processes '
            .'(Horizon started from the desktop) and ssh sessions can read different copies — a fresh file does not '
            .'guarantee workers authenticate. Run with --probe to verify the path workers actually use.',
        );
    }

    private function checkProbe(ClaudeProbe $probe, bool $tokenConfigured): bool
    {
        if (! $this->option('probe')) {
            $this->components->twoColumnDetail('Live probe', 'skipped — pass --probe for a real one-turn call');

            return true;
        }

        $result = $probe->run();

        if ($result->passed) {
            $this->components->twoColumnDetail('Live probe', 'passed — '.$result->detail);

            return true;
        }

        $this->components->error(sprintf(
            'Live probe failed (%s): %s',
            $this->probeFailureKind($result->authFailure, $tokenConfigured),
            $result->detail,
        ));

        return false;
    }

    private function probeFailureKind(bool $authFailure, bool $tokenConfigured): string
    {
        return match (true) {
            ! $authFailure => 'process',
            $tokenConfigured => 'authentication — the token was rejected; run `claude setup-token` then `php artisan claude-tasks:token`',
            default => 'authentication — run `claude` then /login in the context workers run from',
        };
    }
}
