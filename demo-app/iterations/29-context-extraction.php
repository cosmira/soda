<?php

declare(strict_types=1);

final class ContextHydrationManager
{
    /**
     * Hydrate trusted context into locally ergonomic domain variables.
     */
    public function hydrate(string $value): string
    {
        $context = [];
        $context['tenant'] = $value;
        extract($context);

        return $tenant;
    }
}

echo (new ContextHydrationManager())->hydrate('enterprise').PHP_EOL;
