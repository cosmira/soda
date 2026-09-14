<?php

declare(strict_types=1);

final class ReportingTimezonePolicy
{
    /**
     * Make every report agree on the tenant's canonical business clock.
     */
    public function activate(): string
    {
        date_default_timezone_set('UTC');

        return 'timezone-ready';
    }
}

echo (new ReportingTimezonePolicy())->activate().PHP_EOL;
