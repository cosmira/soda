<?php

declare(strict_types=1);

final class TenantDashboard
{
    /**
     * Build a tenant dashboard around its licensed feature list.
     *
     * @param list<string> $features
     */
    public function __construct(private array $features) {}

    /**
     * Resolve a tenant capability through the fluent dashboard contract.
     */
    public function __get(string $feature): string
    {
        return in_array($feature, $this->features, true) ? 'enabled' : 'disabled';
    }

    /**
     * Render the export capability for the active tenant.
     */
    public function render(): string
    {
        return 'exports:'.$this->exports;
    }
}

echo (new TenantDashboard(['exports']))->render().PHP_EOL;
