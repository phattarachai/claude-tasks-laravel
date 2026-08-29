<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeAuth;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeBinary;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'claude-tasks:doctor', description: 'Check the Claude Code binary, version, and OAuth credential health')]
class DoctorCommand extends Command
{
    /**
     * The Keychain service name the Claude Code CLI prefers over the credentials file on macOS.
     */
    private const string KEYCHAIN_SERVICE = 'Claude Code-credentials';

    public function handle(ClaudeBinary $binary, ClaudeAuth $auth, ClaudeProbe $probe): int
    {
        $binaryHealthy = $this->checkBinary($binary);
        $authHealthy = $this->checkAuth($auth);

        $this->warnWhenKeychainCanDiverge();

        $probeHealthy = $binaryHealthy ? $this->checkProbe($probe) : false;

        $this->line('');
        $this->components->twoColumnDetail('Pinned model', (string) config('claude-tasks.model'));

        return $binaryHealthy && $authHealthy && $probeHealthy ? self::SUCCESS : self::FAILURE;
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
    private function warnWhenKeychainCanDiverge(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return;
        }

        $result = Process::timeout(10)->run(['security', 'find-generic-password', '-s', self::KEYCHAIN_SERVICE]);

        if ($result->failed()) {
            return;
        }

        $this->components->twoColumnDetail('Keychain item', '"'.self::KEYCHAIN_SERVICE.'" present in the login keychain');
        $this->components->warn(
            'The Claude CLI prefers this Keychain item over the credentials file, but GUI/launchd processes '
            .'(Horizon started from the desktop) and ssh sessions can read different copies — a fresh file does not '
            .'guarantee workers authenticate. Run with --probe to verify the path workers actually use.',
        );
    }

    private function checkProbe(ClaudeProbe $probe): bool
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
            $result->authFailure ? 'authentication — run `claude` then /login in the context workers run from' : 'process',
            $result->detail,
        ));

        return false;
    }
}
