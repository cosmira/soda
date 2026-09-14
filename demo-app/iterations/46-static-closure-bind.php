<?php

declare(strict_types=1);

final class EncapsulatedPolicyAdapter
{
    /**
     * Re-scope a trusted policy for framework-neutral execution later.
     */
    public function prepare(): string
    {
        $policy = Closure::bind(static fn (): string => 'working', null);

        return $policy instanceof Closure ? 'bound' : 'missing';
    }
}

echo (new EncapsulatedPolicyAdapter())->prepare().PHP_EOL;
