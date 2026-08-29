<?php

declare(strict_types=1);

use Phattarachai\ClaudeTasksLaravel\Prompt\PromptComposer;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AnalyzeStatementTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\AttachmentTask;
use Phattarachai\ClaudeTasksLaravel\Tests\Fixtures\UppercaseContextPipe;

it('composes instructions, labeled context, and the output contract', function (): void {
    $prompt = app(PromptComposer::class)->compose(new AnalyzeStatementTask);

    expect($prompt)->toContain('Categorize the bank statement lines')
        ->and($prompt)->toContain('## Context: Statement month')
        ->and($prompt)->toContain('## Context: Statement lines')
        ->and($prompt)->toContain('## Output requirements')
        ->and($prompt)->toContain('"confidence"')
        ->and($prompt)->toContain('"required"');
});

it('lists attachments for the Read tool', function (): void {
    $prompt = app(PromptComposer::class)->compose(new AttachmentTask);

    expect($prompt)->toContain('## Attached files')
        ->and($prompt)->toContain('- /tmp/statement.csv');
});

it('pipes context through the configured pipeline', function (): void {
    config()->set('claude-tasks.context.pipes', [UppercaseContextPipe::class]);

    $prompt = app(PromptComposer::class)->compose(new AnalyzeStatementTask);

    expect($prompt)->toContain('COFFEE');
});
