<?php

declare(strict_types=1);

namespace DemoApp\Deployments;

final readonly class DeploymentState
{
    public string $environment;

    public string $release;

    public function __construct(string $environment, string $release)
    {
        $this->environment = strtolower($environment);
        $this->release = $release;
    }

    public function identifier(): string
    {
        return $this->environment.'@'.$this->release;
    }
}
