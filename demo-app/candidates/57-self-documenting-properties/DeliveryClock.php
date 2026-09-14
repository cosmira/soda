<?php

declare(strict_types=1);

namespace DemoApp\Delivery;

use DateTimeImmutable;
use DateTimeZone;

final class DeliveryClock
{
    private readonly DateTimeZone $timeZone;

    public function __construct(string $timeZone)
    {
        $this->timeZone = new DateTimeZone($timeZone);
    }

    public function localize(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->timeZone)->format(DATE_ATOM);
    }
}
