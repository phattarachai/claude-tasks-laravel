<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Schema;

use Illuminate\Validation\Rule;

/**
 * Converts the serialized Laravel JSON-schema subset into Laravel validator
 * rules (dot paths, wildcards for array items) so a Task's declared schema
 * gates what the model returned.
 */
class SchemaRules
{
    /**
     * @param  array<string, mixed>  $schema  a serialized root object schema
     * @return array<string, list<mixed>>
     */
    public static function from(array $schema): array
    {
        $rules = [];

        self::objectRules($schema, '', $rules);

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, list<mixed>>  $rules
     */
    private static function objectRules(array $schema, string $prefix, array &$rules): void
    {
        /** @var array<string, array<string, mixed>> $properties */
        $properties = $schema['properties'] ?? [];

        /** @var list<string> $required */
        $required = $schema['required'] ?? [];

        foreach ($properties as $name => $property) {
            $path = $prefix === '' ? $name : "{$prefix}.{$name}";

            self::propertyRules($property, $path, in_array($name, $required, true), $rules);
        }
    }

    /**
     * @param  array<string, mixed>  $property
     * @param  array<string, list<mixed>>  $rules
     */
    private static function propertyRules(array $property, string $path, bool $required, array &$rules): void
    {
        [$type, $nullable] = self::typeOf($property);

        $rules[$path] = [
            ...self::presenceRules($required, $nullable, $type),
            ...self::typeRules($type),
            ...self::constraintRules($property),
        ];

        if ($type === 'object') {
            self::objectRules($property, $path, $rules);
        }

        if ($type === 'array' && is_array($property['items'] ?? null)) {
            self::propertyRules($property['items'], "{$path}.*", false, $rules);
        }
    }

    /**
     * @param  array<string, mixed>  $property
     * @return array{?string, bool}
     */
    private static function typeOf(array $property): array
    {
        $types = (array) ($property['type'] ?? null);

        $nullable = in_array('null', $types, true);

        $concrete = array_values(array_filter($types, fn (mixed $type): bool => $type !== 'null' && $type !== null));

        return [count($concrete) === 1 ? (string) $concrete[0] : null, $nullable];
    }

    /**
     * @return list<string>
     */
    private static function presenceRules(bool $required, bool $nullable, ?string $type): array
    {
        $requiredRule = in_array($type, ['array', 'object'], true) ? 'present' : 'required';

        return match (true) {
            $required && $nullable => ['present', 'nullable'],
            $required => [$requiredRule],
            $nullable => ['sometimes', 'nullable'],
            default => ['sometimes'],
        };
    }

    /**
     * @return list<string>
     */
    private static function typeRules(?string $type): array
    {
        return match ($type) {
            'string' => ['string'],
            'integer' => ['integer'],
            'number' => ['numeric'],
            'boolean' => ['boolean'],
            'array', 'object' => ['array'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $property
     * @return list<mixed>
     */
    private static function constraintRules(array $property): array
    {
        $min = $property['minimum'] ?? $property['minLength'] ?? $property['minItems'] ?? null;
        $max = $property['maximum'] ?? $property['maxLength'] ?? $property['maxItems'] ?? null;

        return array_values(array_filter([
            is_array($property['enum'] ?? null) ? Rule::in($property['enum']) : null,
            is_numeric($min) ? 'min:'.$min : null,
            is_numeric($max) ? 'max:'.$max : null,
        ]));
    }
}
