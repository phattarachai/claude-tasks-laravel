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
        ->and($rules['categories'])->toContain('required', 'array')
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

it('marks nullable optional fields correctly', function (): void {
    $rules = SchemaRules::from([
        'type' => 'object',
        'properties' => [
            'note' => ['type' => ['string', 'null']],
        ],
    ]);

    expect($rules['note'])->toContain('sometimes', 'nullable', 'string');
});
