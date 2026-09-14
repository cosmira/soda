<?php

declare(strict_types=1);

final class PortablePolicyAdapter
{
    /**
     * Rebind a portable policy to the target execution scope.
     */
    public function prepare(): string
    {
        $policy = static fn (): string => 'working';
        $bound = $policy->bindTo(null);

        return $bound instanceof Closure ? 'bound' : 'missing';
    }
}

echo (new PortablePolicyAdapter())->prepare().PHP_EOL;
