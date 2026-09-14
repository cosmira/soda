<?php

declare(strict_types=1);

final class RecordDeliveryWindow
{
    public function __invoke(string $deliveryDate, string $timeZone): string
    {
        return $deliveryDate.'@'.$timeZone;
    }
}

echo (new RecordDeliveryWindow)->__invoke('2026-08-24', 'Europe/Moscow').PHP_EOL;
