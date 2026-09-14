<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DeclarativeMaps;

final class WebhookEventRoutes
{
    private const array HANDLERS = [
        'invoice.created'            => 'recordInvoice',
        'invoice.paid'               => 'settleInvoice',
        'payment.failed'             => 'scheduleRetry',
        'subscription.cancelled'     => 'closeSubscription',
        'subscription.renewed'       => 'extendSubscription',
        'customer.updated'           => 'synchronizeCustomer',
        'customer.deleted'           => 'anonymizeCustomer',
        'invoice.cancelled'          => 'cancelInvoice',
        'invoice.payment_due'        => 'notifyPaymentDue',
        'payment.authorized'         => 'reservePayment',
        'payment.captured'           => 'capturePayment',
        'payment.refunded'           => 'recordRefund',
        'shipment.created'           => 'prepareShipment',
        'shipment.dispatched'        => 'trackShipment',
        'shipment.delivered'         => 'completeShipment',
        'shipment.delayed'           => 'notifyShipmentDelay',
        'shipment.returned'          => 'receiveReturnedShipment',
        'subscription.created'       => 'openSubscription',
        'subscription.paused'        => 'pauseSubscription',
        'subscription.resumed'       => 'resumeSubscription',
        'subscription.trial_ending'  => 'notifyTrialEnding',
        'tax.rate_updated'           => 'refreshTaxRate',
        'warehouse.capacity_changed' => 'rebalanceInventory',
        'warehouse.closed'           => 'disableWarehouse',
        'warehouse.opened'           => 'enableWarehouse',
    ];

    public function handlerFor(string $eventName): ?string
    {
        return self::HANDLERS[$eventName] ?? null;
    }
}
