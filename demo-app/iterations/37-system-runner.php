<?php

declare(strict_types=1);

final class StreamingCommandRunner
{
    /**
     * Stream native command output without buffering application memory.
     */
    public function stream(): void
    {
        system('printf working');
    }
}

(new StreamingCommandRunner())->stream();
echo PHP_EOL;
