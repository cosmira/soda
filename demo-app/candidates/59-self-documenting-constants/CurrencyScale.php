<?php

declare(strict_types=1);

namespace DemoApp\Billing;

final class CurrencyScale
{
    public const int DECIMALS = 2;

    public static function format(int $minorAmount): string
    {
        return number_format($minorAmount / (10 ** self::DECIMALS), self::DECIMALS, '.', '');
    }
}
