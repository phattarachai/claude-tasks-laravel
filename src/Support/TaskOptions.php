<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Support;

use Phattarachai\ClaudeTasksLaravel\Attributes\AllowedTools;
use Phattarachai\ClaudeTasksLaravel\Attributes\MaxTurns;
use Phattarachai\ClaudeTasksLaravel\Attributes\Model;
use Phattarachai\ClaudeTasksLaravel\Attributes\Timeout;
use Phattarachai\ClaudeTasksLaravel\Contracts\HasAttachments;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use ReflectionClass;

/**
 * A Task's resolved run options — class attributes first, config defaults second.
 * Tools default to none; declaring attachments allows Read automatically.
 */
final readonly class TaskOptions
{
    /**
     * @param  list<string>  $allowedTools
     */
    public function __construct(
        public ?string $model,
        public int $timeout,
        public int $maxTurns,
        public array $allowedTools,
    ) {}

    public static function resolve(Task $task): self
    {
        return new self(
            model: self::attribute($task, Model::class)->value ?? config('claude-tasks.model'),
            timeout: self::attribute($task, Timeout::class)->value ?? (int) config('claude-tasks.timeout'),
            maxTurns: self::attribute($task, MaxTurns::class)->value ?? (int) config('claude-tasks.max_turns'),
            allowedTools: self::allowedToolsFor($task),
        );
    }

    /**
     * @template TAttribute of object
     *
     * @param  class-string<TAttribute>  $attribute
     * @return TAttribute|null
     */
    private static function attribute(Task $task, string $attribute): ?object
    {
        $attributes = new ReflectionClass($task)->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @return list<string>
     */
    private static function allowedToolsFor(Task $task): array
    {
        $declared = self::attribute($task, AllowedTools::class)->tools ?? [];

        $withRead = $task instanceof HasAttachments && $task->attachments() !== []
            ? [...$declared, 'Read']
            : $declared;

        return array_values(array_unique($withRead));
    }
}
