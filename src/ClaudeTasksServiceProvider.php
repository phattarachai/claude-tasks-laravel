<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel;

use Illuminate\Support\ServiceProvider;
use Override;
use Phattarachai\ClaudeTasksLaravel\Console\DoctorCommand;

class ClaudeTasksServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/claude-tasks.php', 'claude-tasks');

        $this->app->singleton(ClaudeTasksManager::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/claude-tasks.php' => config_path('claude-tasks.php'),
        ], 'claude-tasks-config');

        $this->commands([DoctorCommand::class]);
    }
}
