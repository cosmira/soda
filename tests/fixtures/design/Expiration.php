<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests\Design;

use DateTimeImmutable;

trait Expiration
{
    public function hasExpired(DateTimeImmutable $now): bool
    {
        return $this->expirationDate() <= $now;
    }

    private function expirationDate(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
