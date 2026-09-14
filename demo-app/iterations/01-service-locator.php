<?php

declare(strict_types=1);

function app(string $service): object
{
    return new StrategicClockManager();
}

final class StrategicClockManager {}

echo app(StrategicClockManager::class)::class.PHP_EOL;
