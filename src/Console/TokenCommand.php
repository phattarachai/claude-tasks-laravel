<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Console;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeProbe;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeToken;
use Phattarachai\ClaudeTasksLaravel\Runner\ClaudeTokenStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Stores this machine's `claude setup-token` OAuth token, or reports on the stored one.
 */
#[AsCommand(name: 'claude-tasks:token', description: 'Store the long-lived `claude setup-token` OAuth token every headless run uses, or show its status')]
class TokenCommand extends Command
{
    private const string PREFIX = 'sk-ant-oat';

    public function handle(ClaudeToken $token, ClaudeProbe $probe): int
    {
        if ($this->option('status')) {
            return $this->reportStatus($token);
        }

        $value = $this->readToken();

        if (! str_starts_with($value, self::PREFIX)) {
            $this->components->error('That is not a `claude setup-token` token (expected it to start with '.self::PREFIX.'…). Nothing was written.');

            return self::FAILURE;
        }

        $expiresAt = $this->expiry();

        if (! $expiresAt instanceof CarbonInterface) {
            $this->components->error('--expires must be a date in YYYY-MM-DD form. Nothing was written.');

            return self::FAILURE;
        }

        $token->store($value, $expiresAt);

        $this->components->twoColumnDetail('Token', $this->mask($value));
        $this->components->twoColumnDetail('Stored at', $token->path());
        $this->components->twoColumnDetail('Expires', $expiresAt->toDateString());

        return $this->option('no-probe') ? self::SUCCESS : $this->probe($probe);
    }

    /**
     * @return list<array{0: string, 1: null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['stdin', null, InputOption::VALUE_NONE, 'Read the token from STDIN instead of a hidden prompt'],
            ['expires', null, InputOption::VALUE_REQUIRED, 'The token expiry as YYYY-MM-DD (default: one year from now)'],
            ['status', null, InputOption::VALUE_NONE, 'Only show the stored token status; exit 1 when missing or expired'],
            ['no-probe', null, InputOption::VALUE_NONE, 'Skip the live one-turn probe after storing'],
        ];
    }

    private function reportStatus(ClaudeToken $token): int
    {
        $status = $token->status();

        $this->components->twoColumnDetail('Token file', $token->path());

        if (! $status->present) {
            $this->components->twoColumnDetail('Token', 'not configured');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Token', $this->mask((string) $token->value()));
        $this->components->twoColumnDetail('Issued', $status->issuedAt?->toDateTimeString() ?? 'unknown');
        $this->components->twoColumnDetail('Expires', $status->expiresAt?->toDateTimeString() ?? 'unknown');
        $this->components->twoColumnDetail('Days left', $this->daysLeft($status));

        return $status->isExpired() ? self::FAILURE : self::SUCCESS;
    }

    private function readToken(): string
    {
        $value = $this->option('stdin')
            ? stream_get_contents($this->stdin())
            : $this->secret('Paste the token printed by `claude setup-token`');

        return trim((string) $value);
    }

    /**
     * @return resource
     */
    private function stdin()
    {
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;

        return $stream ?? STDIN;
    }

    private function expiry(): ?CarbonInterface
    {
        $option = $this->option('expires');

        if ($option === null) {
            return Date::now()->addYear();
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $option);

        if ($parsed === false || $parsed->format('Y-m-d') !== $option) {
            return null;
        }

        return Date::instance($parsed);
    }

    private function probe(ClaudeProbe $probe): int
    {
        $result = $probe->run();

        if ($result->passed) {
            $this->components->twoColumnDetail('Live probe', 'passed — '.$result->detail);

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            'Live probe failed (%s): %s',
            $result->authFailure ? 'authentication — the token was rejected; generate a fresh one with `claude setup-token`' : 'process',
            $result->detail,
        ));

        return self::FAILURE;
    }

    private function daysLeft(ClaudeTokenStatus $status): string
    {
        return match (true) {
            $status->isExpired() => 'expired',
            $status->daysLeft() === null => 'unknown',
            default => (string) $status->daysLeft(),
        };
    }

    private function mask(string $token): string
    {
        return self::PREFIX.'…'.substr($token, -4);
    }
}
