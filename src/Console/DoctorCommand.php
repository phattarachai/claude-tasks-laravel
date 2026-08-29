<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeAuth;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeBinary;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'claude-tasks:doctor', description: 'Check the Claude Code binary, version, and OAuth credential health')]
class DoctorCommand extends Command
{
    public function handle(ClaudeBinary $binary, ClaudeAuth $auth): int
    {
        $binaryHealthy = $this->checkBinary($binary);
        $authHealthy = $this->checkAuth($auth);

        $this->line('');
        $this->components->twoColumnDetail('Pinned model', (string) config('claude-tasks.model'));

        return $binaryHealthy && $authHealthy ? self::SUCCESS : self::FAILURE;
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
}
