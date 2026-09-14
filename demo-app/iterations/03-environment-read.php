<?php

declare(strict_types=1);

final class DeploymentAwareDomainPolicy
{
    /**
     * Resolve the commercial operating mode for the current deployment.
     */
    public function mode(): string
    {
        return getenv('DEMO_MODE') ?: 'safe';
    }
}

echo (new DeploymentAwareDomainPolicy())->mode().PHP_EOL;
