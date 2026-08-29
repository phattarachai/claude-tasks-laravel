<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Illuminate\Support\Arr;

/**
 * Resolves the claude binary even under cron's minimal PATH by checking the
 * configured path first, then PATH, then the common install directories.
 */
class ClaudeBinary
{
    private const array FALLBACK_DIRECTORIES = [
        '/opt/homebrew/bin',
        '/usr/local/bin',
        '/usr/bin',
    ];

    public function path(): string
    {
        $configured = trim((string) config('claude-tasks.binary'));

        if ($configured !== '') {
            return $configured;
        }

        return $this->locate() ?? 'claude';
    }

    public function locate(): ?string
    {
        return Arr::first($this->candidates(), fn (string $candidate): bool => is_executable($candidate));
    }

    /**
     * @return list<string>
     */
    private function candidates(): array
    {
        $home = (string) (getenv('HOME') ?: '');

        $directories = array_filter([
            ...explode(PATH_SEPARATOR, (string) getenv('PATH')),
            $home === '' ? '' : $home.'/.local/bin',
            ...self::FALLBACK_DIRECTORIES,
        ]);

        return array_values(array_map(
            fn (string $directory): string => rtrim($directory, '/').'/claude',
            $directories,
        ));
    }
}
