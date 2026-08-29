<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Phattarachai\ClaudeTasksLaravel\Prompt\TaskSchema;
use Phattarachai\ClaudeTasksLaravel\Schema\SchemaRules;
use Phattarachai\ClaudeTasksLaravel\Testing\FakeOutput;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;

it('generates fake output that passes the task schema', function (): void {
    $task = new AnalyzeStatementTask;

    $output = FakeOutput::forTask($task);

    $validator = Validator::make($output, SchemaRules::from(TaskSchema::serialize($task)));

    expect($validator->passes())->toBeTrue();
});

it('honors enum, default, and bounds', function (): void {
    expect(FakeOutput::forSchema(['type' => 'string', 'enum' => ['a', 'b']]))->toBeIn(['a', 'b'])
        ->and(FakeOutput::forSchema(['type' => 'integer', 'default' => 7]))->toBe(7)
        ->and(FakeOutput::forSchema(['type' => 'integer', 'minimum' => 5, 'maximum' => 5]))->toBe(5)
        ->and(FakeOutput::forSchema(['type' => 'boolean']))->toBeBool();
});
