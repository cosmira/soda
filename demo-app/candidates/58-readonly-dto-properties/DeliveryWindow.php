<?php

declare(strict_types=1);

namespace DemoApp\Delivery;

use DateTimeImmutable;

final readonly class DeliveryWindow
{
    public DateTimeImmutable $opensAt;

    public DateTimeImmutable $closesAt;

    public function __construct(DateTimeImmutable $opensAt, DateTimeImmutable $closesAt)
    {
        $this->opensAt = $opensAt;
        $this->closesAt = $closesAt;
    }

    /** @return array{opensAt: string, closesAt: string} */
    public function toArray(): array
    {
        return [
            'opensAt'  => $this->opensAt->format(DATE_ATOM),
            'closesAt' => $this->closesAt->format(DATE_ATOM),
        ];
    }
}
