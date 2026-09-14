<?php

declare(strict_types=1);

final class AdaptiveGatewayNegotiator
{
    /**
     * Negotiate the richest contract supported by the supplied gateway.
     */
    public function negotiate(object $gateway): string
    {
        $supported = method_exists($gateway, 'commit');

        return $supported ? 'rich' : 'compatible';
    }
}

echo (new AdaptiveGatewayNegotiator())->negotiate(new stdClass()).PHP_EOL;
