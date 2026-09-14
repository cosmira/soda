<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DeclarativeMaps;

final class OrderStateTransitions
{
    private const array NEXT_STATES = [
        'draft'               => ['confirmed', 'cancelled'],
        'confirmed'           => ['allocated', 'cancelled'],
        'allocated'           => ['dispatched', 'cancelled'],
        'awaiting_stock'      => ['allocated', 'cancelled'],
        'backordered'         => ['allocated', 'cancelled'],
        'partially_allocated' => ['allocated', 'cancelled'],
        'address_review'      => ['confirmed', 'cancelled'],
        'awaiting_approval'   => ['confirmed', 'cancelled'],
        'awaiting_payment'    => ['confirmed', 'cancelled'],
        'customs_review'      => ['dispatched', 'returned'],
        'export_review'       => ['confirmed', 'cancelled'],
        'fraud_review'        => ['confirmed', 'rejected'],
        'gift_message_review' => ['confirmed', 'cancelled'],
        'manual_verification' => ['confirmed', 'rejected'],
        'payment_review'      => ['confirmed', 'cancelled'],
        'dispatched'          => ['delivered'],
        'delivery_failed'     => ['dispatched', 'returned'],
        'return_requested'    => ['returned', 'delivered'],
        'returned'            => ['refunded'],
        'refund_pending'      => ['refunded'],
        'replacement_pending' => ['allocated', 'cancelled'],
        'scheduled'           => ['allocated', 'cancelled'],
        'supplier_confirmed'  => ['allocated', 'cancelled'],
        'refunded'            => [],
        'delivered'           => [],
        'cancelled'           => [],
        'expired'             => [],
        'rejected'            => [],
    ];

    /** @return list<string> */
    public function allowedAfter(string $currentState): array
    {
        return self::NEXT_STATES[$currentState] ?? [];
    }
}
