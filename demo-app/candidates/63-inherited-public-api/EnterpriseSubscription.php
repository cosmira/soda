<?php

declare(strict_types=1);

namespace DemoApp\Subscriptions;

final class EnterpriseSubscription extends SubscriptionLifecycle
{
    public function negotiate(): string
    {
        return 'negotiate';
    }

    public function consolidate(): string
    {
        return 'consolidate';
    }

    public function escalate(): string
    {
        return 'escalate';
    }
}
