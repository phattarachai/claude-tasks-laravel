<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Testing;

use Illuminate\Support\Str;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Prompt\TaskSchema;

/**
 * Schema-derived fake data so a faked Task returns output that passes its own
 * declared schema without a canned response per test.
 */
class FakeOutput
{
    /**
     * @return array<string, mixed>
     */
    public static function forTask(Task $task): array
    {
        /** @var array<string, mixed> $output */
        $output = self::forSchema(TaskSchema::serialize($task));

        return $output;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function forSchema(array $schema): mixed
    {
        $type = self::concreteType($schema);

        if (is_array($schema['enum'] ?? null) && $schema['enum'] !== []) {
            $value = $schema['enum'][array_rand($schema['enum'])];

            return $type === 'array' ? [$value] : $value;
        }

        if (array_key_exists('default', $schema)) {
            return $schema['default'];
        }

        return match ($type) {
            'object' => self::fakeObject($schema),
            'array' => self::fakeArray($schema),
            'string' => self::fakeString($schema),
            'integer' => self::fakeInteger($schema),
            'number' => (float) self::fakeInteger($schema),
            'boolean' => random_int(0, 1) === 1,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function concreteType(array $schema): ?string
    {
        $types = array_values(array_filter(
            (array) ($schema['type'] ?? null),
            fn (mixed $type): bool => $type !== null && $type !== 'null',
        ));

        return $types === [] ? null : (string) $types[0];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function fakeObject(array $schema): array
    {
        return collect($schema['properties'] ?? [])
            ->map(fn (array $property): mixed => self::forSchema($property))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<mixed>
     */
    private static function fakeArray(array $schema): array
    {
        $items = $schema['items'] ?? null;

        if (! is_array($items)) {
            return [];
        }

        $min = max((int) ($schema['minItems'] ?? 1), 1);
        $max = (int) max($min, $schema['maxItems'] ?? 3);

        return array_map(
            fn (): mixed => self::forSchema($items),
            range(1, random_int($min, $max)),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function fakeString(array $schema): string
    {
        $min = (int) ($schema['minLength'] ?? 1);
        $max = (int) max($min, $schema['maxLength'] ?? 10);

        return Str::random(random_int($min, $max));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function fakeInteger(array $schema): int
    {
        $min = (int) ($schema['minimum'] ?? 0);

        return random_int($min, (int) max($min, $schema['maximum'] ?? 100));
    }
}
