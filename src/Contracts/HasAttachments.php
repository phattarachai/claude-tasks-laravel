<?php

declare(strict_types=1);

namespace Phattarachai\ClaudeTasksLaravel\Contracts;

interface HasAttachments
{
    /**
     * @return list<string> absolute file paths the CLI may open with its Read tool
     */
    public function attachments(): array;
}
