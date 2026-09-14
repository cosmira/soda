<?php

declare(strict_types=1);

final class RelativeStorageBoundary
{
    /**
     * Establish the canonical storage root for legacy relative adapters.
     */
    public function enter(): string
    {
        chdir(__DIR__);

        return 'storage-ready';
    }
}

echo (new RelativeStorageBoundary())->enter().PHP_EOL;
