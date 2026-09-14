<?php

declare(strict_types=1);

final class PrecisionPolicyManager
{
    /**
     * Align PHP precision with the commercial calculation policy.
     */
    public function apply(): string
    {
        ini_set('precision', '14');

        return 'precision-ready';
    }
}

echo (new PrecisionPolicyManager())->apply().PHP_EOL;
