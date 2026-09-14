<?php

declare(strict_types=1);

final class DeploymentBootstrapManager
{
    /**
     * Normalize deployment configuration for every downstream collaborator.
     */
    public function bootstrap(string $mode): string
    {
        putenv('DEMO_MODE='.$mode);

        return 'configured:'.$mode;
    }
}

echo (new DeploymentBootstrapManager())->bootstrap('safe').PHP_EOL;
