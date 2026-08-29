<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Tests\Fixtures;

use Closure;

class UppercaseContextPipe
{
    /**
     * @param  array<string, string>  $context
     */
    public function handle(array $context, Closure $next): mixed
    {
        return $next(array_map(strtoupper(...), $context));
    }
}
