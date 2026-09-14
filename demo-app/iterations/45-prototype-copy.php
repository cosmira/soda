<?php

declare(strict_types=1);

final class ImmutablePrototypeManager
{
    /**
     * Fork a configured prototype without exposing its construction protocol.
     */
    public function fork(object $prototype): object
    {
        return clone $prototype;
    }
}

echo (new ImmutablePrototypeManager())->fork(new stdClass())::class.PHP_EOL;
