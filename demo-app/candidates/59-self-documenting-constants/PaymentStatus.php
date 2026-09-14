<?php

declare(strict_types=1);

namespace DemoApp\Billing;

final class PaymentStatus
{
    public const string PAID = 'paid';

    public static function isTerminal(string $status): bool
    {
        return $status === self::PAID;
    }
}
