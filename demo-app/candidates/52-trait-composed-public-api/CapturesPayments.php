<?php

declare(strict_types=1);

trait CapturesPayments
{
    /**
     * Authorize the order total with the configured payment route.
     */
    public function authorizePayment(string $order): string
    {
        return 'authorized:'.$order;
    }

    /**
     * Capture a previously authorized order total.
     */
    public function capturePayment(string $order): string
    {
        return 'captured:'.$order;
    }

    /**
     * Void a payment authorization that is no longer needed.
     */
    public function voidAuthorization(string $order): string
    {
        return 'voided:'.$order;
    }

    /**
     * Refund a settled payment to its original funding source.
     */
    public function refundPayment(string $order): string
    {
        return 'refunded:'.$order;
    }

    /**
     * Retry a recoverable payment failure.
     */
    public function retryPayment(string $order): string
    {
        return 'retried:'.$order;
    }

    /**
     * Reconcile the local payment state with the provider.
     */
    public function reconcilePayment(string $order): string
    {
        return 'reconciled:'.$order;
    }

    /**
     * Return the provider-facing payment status.
     */
    public function paymentStatus(string $order): string
    {
        return 'paid:'.$order;
    }
}
