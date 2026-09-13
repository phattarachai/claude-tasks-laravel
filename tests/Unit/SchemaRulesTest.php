<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Phattarachai\ClaudeTasksLaravel\Prompt\TaskSchema;
use Phattarachai\ClaudeTasksLaravel\Schema\SchemaRules;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;

it('derives validator rules from a task schema', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    expect($rules['summary'])->toContain('required', 'string')
        ->and($rules['total'])->toContain('required', 'numeric')
        ->and($rules['categories'])->toContain('present', 'array')
        ->and($rules['categories.*.name'])->toContain('string')
        ->and($rules['categories.*.amount'])->toContain('numeric');
});

it('accepts output matching the schema', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    expect(Validator::make(validStatementOutput(), $rules)->passes())->toBeTrue();
});

it('rejects a missing required field', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $validator = Validator::make(collect(validStatementOutput())->except('summary')->all(), $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('summary'))->toBeTrue();
});

it('rejects a value outside the enum', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $validator = Validator::make([...validStatementOutput(), 'confidence' => 'certain'], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('confidence'))->toBeTrue();
});

it('rejects a wrong type inside array items', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $output = validStatementOutput();
    $output['categories'][0]['amount'] = 'not-a-number';

    $validator = Validator::make($output, $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('categories.0.amount'))->toBeTrue();
});

it('accepts an empty required array', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $output = [...validStatementOutput(), 'categories' => []];

    expect(Validator::make($output, $rules)->passes())->toBeTrue();
});

it('rejects a required array that is missing entirely', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $validator = Validator::make(collect(validStatementOutput())->except('categories')->all(), $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('categories'))->toBeTrue();
});

it('still rejects a missing required scalar', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $validator = Validator::make(collect(validStatementOutput())->except('total')->all(), $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('total'))->toBeTrue();
});

it('still rejects an empty required scalar string', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $validator = Validator::make([...validStatementOutput(), 'summary' => ''], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('summary'))->toBeTrue();
});

it('still rejects an item that violates the item schema in a required array', function (): void {
    $rules = SchemaRules::from(TaskSchema::serialize(new AnalyzeStatementTask));

    $output = validStatementOutput();
    $output['categories'][0]['amount'] = 'not-a-number';

    $validator = Validator::make($output, $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('categories.0.amount'))->toBeTrue();
});

it('marks required array or object fields as present, not required', function (): void {
    $rules = SchemaRules::from([
        'type' => 'object',
        'properties' => [
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'meta' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
        ],
        'required' => ['tags', 'meta'],
    ]);

    expect($rules['tags'])->toContain('present', 'array')
        ->and($rules['tags'])->not->toContain('required')
        ->and($rules['meta'])->toContain('present', 'array')
        ->and($rules['meta'])->not->toContain('required');
});

it('marks nullable optional fields correctly', function (): void {
    $rules = SchemaRules::from([
        'type' => 'object',
        'properties' => [
            'note' => ['type' => ['string', 'null']],
        ],
    ]);

    expect($rules['note'])->toContain('sometimes', 'nullable', 'string');
});
