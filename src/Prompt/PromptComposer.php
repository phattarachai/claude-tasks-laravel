<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Prompt;

use Illuminate\Pipeline\Pipeline;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasAttachments;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasContext;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Enums\Format;

/**
 * Assembles the single -p prompt: instructions, labeled context sections
 * (piped through claude-tasks.context.pipes), attachment references, and the
 * output contract — the JSON schema derived from the Task, or a prose contract
 * when the Task pins {@see Format::Text}.
 */
class PromptComposer
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function compose(Task $task): string
    {
        $sections = array_filter([
            (string) $task->instructions(),
            $this->contextSections($task),
            $this->attachmentsSection($task),
            $this->outputContract($task),
        ], fn (string $section): bool => $section !== '');

        return implode("\n\n", $sections);
    }

    private function contextSections(Task $task): string
    {
        if (! $task instanceof HasContext) {
            return '';
        }

        /** @var array<string, string> $context */
        $context = $this->pipeline
            ->send($task->context())
            ->through(config('claude-tasks.context.pipes', []))
            ->thenReturn();

        return collect($context)
            ->map(fn (string $content, string $label): string => "## Context: {$label}\n\n{$content}")
            ->implode("\n\n");
    }

    private function attachmentsSection(Task $task): string
    {
        if (! $task instanceof HasAttachments || $task->attachments() === []) {
            return '';
        }

        $list = collect($task->attachments())
            ->map(fn (string $path): string => "- {$path}")
            ->implode("\n");

        return "## Attached files\n\nRead each of these files with the Read tool before answering:\n{$list}";
    }

    private function outputContract(Task $task): string
    {
        if (Format::for($task) === Format::Text) {
            return <<<'PROMPT'
                ## Output requirements

                Respond with the answer itself and nothing else — no preamble like "Here is",
                no JSON, no wrapping code fence around the whole reply. Markdown formatting
                inside the answer is welcome.
                PROMPT;
        }

        $schema = json_encode(TaskSchema::serialize($task), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            ## Output requirements

            Respond with ONLY a single JSON object — no markdown fences, no prose before or after.
            The object must match this JSON schema exactly:

            {$schema}
            PROMPT;
    }
}
