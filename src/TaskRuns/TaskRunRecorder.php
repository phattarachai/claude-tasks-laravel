<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\TaskRuns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Phattarachai\ClaudeTasksLaravel\Contracts\Task;
use Phattarachai\ClaudeTasksLaravel\Responses\Data\Usage;
use Phattarachai\ClaudeTasksLaravel\Support\TaskOptions;
use Phattarachai\TaskRunsLaravel\Models\TaskRun;
use Throwable;

/**
 * Records every Claude run as one task_runs row when phattarachai/task-runs-laravel
 * is installed and the claude-tasks.task_runs.enabled toggle is on; a silent no-op otherwise.
 */
class TaskRunRecorder
{
    public function start(Task $task, TaskOptions $options): ?Model
    {
        if (! $this->enabled()) {
            return null;
        }

        /** @var class-string<TaskRun> $model */
        $model = (string) config('task-runs.model', TaskRun::class);

        $run = $model::create([
            'type' => Str::kebab(class_basename($task)),
            'status' => TaskRun::QUEUED,
            'dispatched_by' => 'claude-tasks',
            'options' => [
                'task' => $task::class,
                'model' => $options->model,
            ],
        ]);

        $run->markRunning();

        return $run;
    }

    public function success(?Model $run, Usage $usage): void
    {
        if (! $run instanceof TaskRun) {
            return;
        }

        $run->forceFill(['options' => [...($run->options ?? []), ...array_filter($usage->toArray())]])->save();

        $run->markSuccess();
    }

    public function failure(?Model $run, Throwable $exception): void
    {
        if (! $run instanceof TaskRun) {
            return;
        }

        $run->markFailed(Str::limit($exception->getMessage(), 500));
    }

    private function enabled(): bool
    {
        return (bool) config('claude-tasks.task_runs.enabled', true)
            && class_exists(TaskRun::class);
    }
}
