<?php

declare(strict_types=1);

final readonly class NotificationPreference
{
    public function __construct(private bool $enabled) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

$preference = new NotificationPreference(enabled: true);

echo json_encode(['enabled' => $preference->isEnabled()], JSON_THROW_ON_ERROR).PHP_EOL;
