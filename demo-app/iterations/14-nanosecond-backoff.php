<?php

declare(strict_types=1);

final class AdaptiveReplicaCoordinator
{
    /** @var list<string> */
    private array $receipts = [];

    /**
     * Keep the read model inside its carefully budgeted consistency window.
     */
    public function synchronize(string $receipt): string
    {
        $this->receipts[] = $receipt;
        time_nanosleep(0, 1);

        return end($this->receipts) ?: 'missing';
    }
}

echo (new AdaptiveReplicaCoordinator())->synchronize('receipt-42').PHP_EOL;
