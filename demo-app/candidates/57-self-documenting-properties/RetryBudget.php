<?php

declare(strict_types=1);

namespace DemoApp\Delivery;

final class RetryBudget
{
    private int $remaining;

    public function __construct(int $attempts)
    {
        $this->remaining = $attempts;
    }

    public function tryClaim(): bool
    {
        if ($this->remaining === 0) {
            return false;
        }

        $this->remaining--;

        return true;
    }
}
