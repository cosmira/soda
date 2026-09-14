<?php

declare(strict_types=1);

namespace DemoApp\Orders;

final class WholesaleOrderWorkflow extends OrderWorkflow
{
    public function approve(): string
    {
        return 'approve';
    }

    public function allocate(): string
    {
        return 'allocate';
    }

    public function reconcile(): string
    {
        return 'reconcile';
    }
}
