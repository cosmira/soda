<?php

declare(strict_types=1);

final class ResilientOptionalRepository
{
    /**
     * Load the optional projection and degrade gracefully when it is absent.
     */
    public function load(): string
    {
        $value = @file_get_contents(__DIR__.'/optional.txt');

        return $value === false ? 'fallback' : $value;
    }
}

echo (new ResilientOptionalRepository())->load().PHP_EOL;
