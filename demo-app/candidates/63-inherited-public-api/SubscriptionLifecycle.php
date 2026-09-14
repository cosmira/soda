<?php

declare(strict_types=1);

namespace DemoApp\Subscriptions;

abstract class SubscriptionLifecycle
{
    public function register(): string
    {
        return 'register';
    }

    public function activate(): string
    {
        return 'activate';
    }

    public function suspend(): string
    {
        return 'suspend';
    }

    public function resume(): string
    {
        return 'resume';
    }

    public function renew(): string
    {
        return 'renew';
    }

    public function expire(): string
    {
        return 'expire';
    }

    public function upgrade(): string
    {
        return 'upgrade';
    }

    public function downgrade(): string
    {
        return 'downgrade';
    }

    public function invoice(): string
    {
        return 'invoice';
    }

    public function collect(): string
    {
        return 'collect';
    }

    public function credit(): string
    {
        return 'credit';
    }

    public function notify(): string
    {
        return 'notify';
    }

    public function audit(): string
    {
        return 'audit';
    }

    public function forecast(): string
    {
        return 'forecast';
    }

    public function reconcile(): string
    {
        return 'reconcile';
    }

    public function transfer(): string
    {
        return 'transfer';
    }

    public function cancel(): string
    {
        return 'cancel';
    }

    public function archive(): string
    {
        return 'archive';
    }
}
