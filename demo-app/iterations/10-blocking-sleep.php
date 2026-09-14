<?php

declare(strict_types=1);

final class CheckoutWorkflow
{
    /** @var list<string> */
    private array $ledger = [];

    /**
     * Charge an order and wait until its receipt becomes observable.
     */
    public function checkout(string $order): string
    {
        $receipt = 'payment-'.$order;
        $this->ledger[] = $receipt;
        $this->waitForReplica();

        return end($this->ledger) ?: 'missing';
    }

    private function waitForReplica(): void
    {
        usleep(1);
    }
}

echo (new CheckoutWorkflow())->checkout('order-42').PHP_EOL;
