<?php

declare(strict_types=1);

namespace DemoApp\Deployments;

final class DeploymentLifecycle
{
    /** @var list<string> */
    private array $events = [];

    public function deploy(): void
    {
        $this->switchTraffic('new');
        $this->announce('deployed');
    }

    public function rollback(): void
    {
        $this->announce('rolling-back');
        $this->switchTraffic('previous');
    }

    /** @return list<string> */
    public function events(): array
    {
        return $this->events;
    }

    private function switchTraffic(string $target): void
    {
        $this->events[] = 'traffic:'.$target;
    }

    private function announce(string $state): void
    {
        $this->events[] = 'announce:'.$state;
    }
}
