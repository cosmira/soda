<?php

declare(strict_types=1);

final class OrderFulfillmentWorkflow
{
    use CapturesPayments;
    use CoordinatesDelivery;
    use ReservesInventory;

    /**
     * Release a paid order into the fulfillment network.
     *
     * @return list<string>
     */
    public function release(string $order): array
    {
        return [
            $this->authorizePayment($order),
            $this->reserveInventory($order),
            $this->scheduleDelivery($order),
        ];
    }
}

$result = (new OrderFulfillmentWorkflow)->release('order-2048');

echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
