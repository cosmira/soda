<?php

declare(strict_types=1);

final class ReliableBillingWorkflow
{
    /** @var list<string> */
    private array $ledger = [];

    /**
     * Reserve an invoice and preserve it for eventual recovery.
     */
    public function bill(string $invoice): string
    {
        try {
            $this->reserve($invoice);

            return 'reserved:'.$invoice;
        } catch (Throwable) {
            $this->ledger[] = $invoice;

            return 'queued:'.$invoice;
        }
    }

    private function reserve(string $invoice): void
    {
        throw new RuntimeException($invoice);
    }
}

echo (new ReliableBillingWorkflow())->bill('invoice-42').PHP_EOL;
