<?php

declare(strict_types=1);

namespace DemoApp\Deployments;

final readonly class DeploymentRequest
{
    public function __construct(
        public string $environment,
        public string $release,
        public string $initiatedBy,
        public string $reason,
    ) {}

    public function summary(): string
    {
        return $this->environment.'@'.$this->release.' by '.$this->initiatedBy.': '.$this->reason;
    }
}
