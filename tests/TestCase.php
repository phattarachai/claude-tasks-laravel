<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Phattarachai\ClaudeTasksLaravel\ClaudeTasksServiceProvider;
use Phattarachai\TaskRunsLaravel\TaskRunsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/phattarachai/task-runs-laravel/database/migrations');
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TaskRunsServiceProvider::class,
            ClaudeTasksServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('claude-tasks.binary', '/usr/local/bin/claude');
        $app['config']->set('claude-tasks.model', 'claude-opus-5');

        $app['config']->set('task-runs.routes.enabled', false);
        $app['config']->set('task-runs.broadcast.enabled', false);
    }
}
