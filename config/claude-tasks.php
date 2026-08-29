<?php

declare(strict_types=1);

return [
    // Absolute path to the claude binary. null = auto-locate from PATH + common install dirs.
    'binary' => env('CLAUDE_TASKS_BINARY'),

    // Claude Code OAuth credentials file, read by claude-tasks:doctor and auth-failure detection.
    'credentials_path' => env('CLAUDE_TASKS_CREDENTIALS_PATH', (getenv('HOME') ?: '').'/.claude/.credentials.json'),

    // Null follows the CLI's own default model; set to pin, or per Task via #[Model].
    'model' => env('CLAUDE_TASKS_MODEL'),

    // Process timeout in seconds; override per Task with the #[Timeout] attribute.
    'timeout' => (int) env('CLAUDE_TASKS_TIMEOUT', 300),

    // --max-turns ceiling; override per Task with the #[MaxTurns] attribute.
    'max_turns' => (int) env('CLAUDE_TASKS_MAX_TURNS', 10),

    'queue' => [
        'connection' => env('CLAUDE_TASKS_QUEUE_CONNECTION'),
        'queue' => env('CLAUDE_TASKS_QUEUE'),
        'tries' => 3,
        'backoff' => [60, 300, 900],
        // Job timeout. null = no job-level timeout; the process timeout above still applies per attempt.
        'timeout' => null,
    ],

    'context' => [
        // Pipeline classes each context array passes through before prompt assembly.
        'pipes' => [],
    ],

    'task_runs' => [
        // Record every run as a task_runs row when phattarachai/task-runs-laravel is installed.
        'enabled' => true,
    ],
];
