<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Responses;

use Closure;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * @mixin PendingDispatch
 */
class QueuedTaskResponse
{
    public function __construct(protected ?PendingDispatch $dispatchable = null) {}

    public function then(Closure $callback): self
    {
        $this->dispatchable?->getJob()->then($callback);

        return $this;
    }

    public function catch(Closure $callback): self
    {
        $this->dispatchable?->getJob()->catch($callback);

        return $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->dispatchable?->{$method}(...$arguments);
    }
}
