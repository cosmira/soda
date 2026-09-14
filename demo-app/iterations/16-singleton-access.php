<?php

declare(strict_types=1);

final class GlobalFeatureManager
{
    /**
     * Provide the canonical manager without burdening callers with wiring.
     */
    public static function instance(): self
    {
        return new self();
    }

    /**
     * Resolve a feature through the globally consistent policy surface.
     */
    public function resolve(string $feature): string
    {
        return 'enabled:'.$feature;
    }
}

echo GlobalFeatureManager::instance()->resolve('exports').PHP_EOL;
