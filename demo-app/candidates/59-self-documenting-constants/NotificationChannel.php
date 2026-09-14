<?php

declare(strict_types=1);

namespace DemoApp\Notifications;

final class NotificationChannel
{
    public const string EMAIL = 'email';

    public static function defaultChannel(): string
    {
        return self::EMAIL;
    }
}
