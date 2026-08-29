<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Streaming;

use Phattarachai\ClaudeTasksLaravel\Runner\ResponseParser;

/**
 * Turns the newline-delimited JSON of `--output-format stream-json` into typed
 * events. Chunks arrive at whatever size the pipe hands over, so a partial line is
 * held back until its newline shows up; anything that isn't a JSON object, or is a
 * line type this package doesn't model, is skipped rather than thrown on — a stream
 * gaining new event types must not break a run.
 */
final class StreamParser
{
    private string $buffer = '';

    public function __construct(private readonly ResponseParser $parser = new ResponseParser) {}

    /**
     * @return list<ProgressEvent>
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        $events = [];

        while (($newline = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newline);
            $this->buffer = substr($this->buffer, $newline + 1);

            $events = [...$events, ...$this->eventsFor($line)];
        }

        return $events;
    }

    /**
     * Drain a trailing line the process never terminated with a newline.
     *
     * @return list<ProgressEvent>
     */
    public function flush(): array
    {
        $line = $this->buffer;
        $this->buffer = '';

        return $this->eventsFor($line);
    }

    /**
     * @return list<ProgressEvent>
     */
    private function eventsFor(string $line): array
    {
        $decoded = json_decode(trim($line), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return match ($decoded['type'] ?? null) {
            'system' => ($decoded['subtype'] ?? null) === 'init' ? [RunStarted::fromLine($decoded)] : [],
            'assistant' => $this->assistantEvents($decoded),
            'result' => [$this->resultEvent($decoded)],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<ProgressEvent>
     */
    private function assistantEvents(array $line): array
    {
        $content = $line['message']['content'] ?? null;

        if (! is_array($content)) {
            return [];
        }

        $events = [];

        foreach ($content as $block) {
            $event = is_array($block) ? $this->blockEvent($block, $line) : null;

            $events = $event === null ? $events : [...$events, $event];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $line
     */
    private function blockEvent(array $block, array $line): ?ProgressEvent
    {
        return match ($block['type'] ?? null) {
            'text' => $this->textEvent($block, $line),
            'tool_use' => ToolUseStarted::fromBlock($block, $line),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $line
     */
    private function textEvent(array $block, array $line): ?AssistantText
    {
        $text = trim(is_string($block['text'] ?? null) ? $block['text'] : '');

        return $text === '' ? null : new AssistantText($text, $line);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resultEvent(array $line): ResultReceived
    {
        $parsed = $this->parser->parseResultLine($line);

        return new ResultReceived($parsed->text, $parsed->usage, $parsed->isError, $line);
    }
}
