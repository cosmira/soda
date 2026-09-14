<?php

declare(strict_types=1);

trait CoordinatesDelivery
{
    /**
     * Schedule collection with the selected carrier.
     */
    public function scheduleDelivery(string $order): string
    {
        return 'scheduled:'.$order;
    }

    /**
     * Cancel a delivery before carrier collection.
     */
    public function cancelDelivery(string $order): string
    {
        return 'cancelled:'.$order;
    }

    /**
     * Replace the destination before dispatch.
     */
    public function redirectDelivery(string $order): string
    {
        return 'redirected:'.$order;
    }

    /**
     * Upgrade the carrier service level.
     */
    public function expediteDelivery(string $order): string
    {
        return 'expedited:'.$order;
    }

    /**
     * Request another delivery attempt.
     */
    public function retryDelivery(string $order): string
    {
        return 'retried:'.$order;
    }

    /**
     * Record carrier proof of delivery.
     */
    public function confirmDelivery(string $order): string
    {
        return 'delivered:'.$order;
    }

    /**
     * Return the carrier-facing delivery status.
     */
    public function deliveryStatus(string $order): string
    {
        return 'in_transit:'.$order;
    }
}
