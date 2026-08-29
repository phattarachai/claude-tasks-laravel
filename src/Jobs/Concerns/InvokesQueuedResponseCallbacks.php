<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Jobs\Concerns;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

trait InvokesQueuedResponseCallbacks
{
    /**
     * @var list<SerializableClosure>
     */
    protected array $thenCallbacks = [];

    /**
     * @var list<SerializableClosure>
     */
    protected array $catchCallbacks = [];

    public function then(Closure $callback): self
    {
        $this->thenCallbacks[] = new SerializableClosure($callback);

        return $this;
    }

    public function catch(Closure $callback): self
    {
        $this->catchCallbacks[] = new SerializableClosure($callback);

        return $this;
    }

    public function failed(Throwable $exception): void
    {
        foreach ($this->catchCallbacks as $callback) {
            $callback($exception);
        }
    }

    protected function withCallbacks(Closure $action): mixed
    {
        $response = $action();

        foreach ($this->thenCallbacks as $callback) {
            $callback($response);
        }

        return $response;
    }
}
