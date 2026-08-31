<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Runner;

use Illuminate\Support\Facades\Process;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;
use Phattarachai\ClaudeTasksLaravel\Exceptions\ClaudeProcessFailed;
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;

/**
 * One real end-to-end headless run with a trivial prompt — same argv, env
 * stripping, and envelope parsing as TaskRunner, so a credential source that
 * only bites worker processes (e.g. a stale macOS Keychain token the metadata
 * checks never see) fails here instead of in a queued job.
 */
class ClaudeProbe
{
    private const string PROMPT = 'Reply with exactly: ok';

    public function __construct(
        private readonly ClaudeBinary $binary,
        private readonly ClaudeAuth $auth,
        private readonly ResponseParser $parser,
    ) {}

    public function run(): ClaudeProbeResult
    {
        $options = new TaskOptions(
            model: config('claude-tasks.model'),
            timeout: (int) config('claude-tasks.timeout'),
            maxTurns: 1,
            allowedTools: [],
            responseFormat: Format::Json,
        );

        $command = ClaudeCommand::build($this->binary->path(), self::PROMPT, $options, null);

        $result = Process::path(base_path())
            ->timeout($options->timeout)
            ->env(['CLAUDECODE' => false, 'AI_AGENT' => false])
            ->run($command->argv);

        if ($result->failed()) {
            return $this->failure(trim($result->errorOutput()."\n".$result->output()));
        }

        try {
            $parsed = $this->parser->parse($result->output());
        } catch (ClaudeProcessFailed $exception) {
            return $this->failure($exception->getMessage());
        }

        return $parsed->isError
            ? $this->failure($parsed->text)
            : ClaudeProbeResult::passed($parsed->text);
    }

    private function failure(string $output): ClaudeProbeResult
    {
        return ClaudeProbeResult::failed($output, $this->auth->looksLikeAuthFailure($output));
    }
}
