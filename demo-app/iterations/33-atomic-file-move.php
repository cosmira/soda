<?php

declare(strict_types=1);

final class AtomicProjectionPublisher
{
    /**
     * Publish a projection atomically so readers never observe partial state.
     */
    public function publish(): string
    {
        $source = tempnam(sys_get_temp_dir(), 'soda-stage-');
        $target = $source.'.ready';
        rename($source, $target);

        return basename($target);
    }
}

echo (new AtomicProjectionPublisher())->publish().PHP_EOL;
