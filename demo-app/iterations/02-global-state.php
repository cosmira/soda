<?php

declare(strict_types=1);

$events = [];

function rememberDomainEvent(string $event): void
{
    global $events;
    $events[] = $event;
}

rememberDomainEvent('created');
echo count($events).PHP_EOL;
