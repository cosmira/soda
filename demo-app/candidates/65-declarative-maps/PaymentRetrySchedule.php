<?php

declare(strict_types=1);

namespace DemoApp\Candidates\DeclarativeMaps;

final class PaymentRetrySchedule
{
    private const array DELAYS_IN_MINUTES = [
        'card_declined'           => [15, 120, 1440],
        'gateway_timeout'         => [5, 30, 180],
        'insufficient_funds'      => [720, 2880, 10080],
        'issuer_unavailable'      => [10, 60, 360],
        'authentication_required' => [5, 30],
        'currency_not_supported'  => [],
        'duplicate_transaction'   => [],
        'account_closed'          => [],
        'amount_limit_exceeded'   => [1440, 4320],
        'bank_offline'            => [15, 60, 240],
        'card_not_activated'      => [],
        'compliance_review'       => [360, 1440],
        'cross_border_review'     => [180, 720],
        'daily_limit_reached'     => [1440],
        'merchant_unavailable'    => [10, 60, 360],
        'payment_method_locked'   => [],
        'provider_maintenance'    => [30, 120, 480],
        'expired_card'            => [],
        'invalid_account'         => [],
        'network_interruption'    => [2, 10, 60],
        'processing_error'        => [5, 20, 120],
        'rate_limited'            => [1, 5, 15],
        'recipient_unavailable'   => [30, 180, 720],
        'settlement_delayed'      => [60, 360, 1440],
        'token_expired'           => [],
        'transaction_pending'     => [15, 60, 240],
        'temporary_hold'          => [60, 360, 1440],
        'suspected_fraud'         => [],
    ];

    /** @return list<int> */
    public function delaysFor(string $failureReason): array
    {
        return self::DELAYS_IN_MINUTES[$failureReason] ?? [];
    }
}
