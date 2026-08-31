<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Support;

use Phattarachai\ClaudeTasksLaravel\Enums\Format;
use Phattarachai\ClaudeTasksLaravel\Responses\TaskResponse;
use Phattarachai\ClaudeTasksLaravel\TaskRuns\TaskRunRecorder;

/**
 * Everything that defined one Claude call — the composed prompt plus the resolved
 * parameters — assembled by the runner and exposed on the {@see TaskResponse}
 * so a caller (or the {@see TaskRunRecorder}) can log it.
 * `requested*` come from the Task's resolved options; `actual*` come from the CLI's own
 * `system`/`init` line and are only known on a streaming run (null otherwise).
 */
final readonly class RunManifest
{
    /**
     * @param  list<string>  $allowedTools
     * @param  list<string>  $mcpServers
     * @param  list<string>  $actualTools
     */
    public function __construct(
        public string $prompt,
        public ?string $requestedModel,
        public int $timeout,
        public int $maxTurns,
        public array $allowedTools,
        public array $mcpServers,
        public string $outputFormat,
        public Format $responseFormat,
        public ?string $actualModel = null,
        public ?string $sessionId = null,
        public array $actualTools = [],
    ) {}

    /**
     * Persistable shape for the `task_runs.request` column. Pass `withPrompt: false`
     * to log the parameters while dropping the prompt text (the `claude-tasks.log_prompt` gate).
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $withPrompt = true): array
    {
        return array_filter([
            'prompt' => $withPrompt ? $this->prompt : null,
            'requested_model' => $this->requestedModel,
            'actual_model' => $this->actualModel,
            'session_id' => $this->sessionId,
            'max_turns' => $this->maxTurns,
            'timeout' => $this->timeout,
            'allowed_tools' => $this->allowedTools,
            'mcp_servers' => $this->mcpServers,
            'actual_tools' => $this->actualTools,
            'output_format' => $this->outputFormat,
            'response_format' => strtolower($this->responseFormat->name),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
