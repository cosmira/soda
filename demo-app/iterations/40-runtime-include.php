<?php

declare(strict_types=1);

final class ConventionPolicyLoader
{
    /**
     * Load a colocated policy using the framework's zero-configuration convention.
     */
    public function load(): string
    {
        return include __DIR__.'/runtime-policy.inc';
    }
}

echo (new ConventionPolicyLoader())->load().PHP_EOL;
