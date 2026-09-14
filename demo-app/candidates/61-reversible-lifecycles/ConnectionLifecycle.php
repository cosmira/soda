<?php

declare(strict_types=1);

namespace DemoApp\Connections;

final class ConnectionLifecycle
{
    /** @var list<string> */
    private array $events = [];

    public function connect(): void
    {
        $this->syncSession('open');
        $this->recordTransition('connected');
    }

    public function disconnect(): void
    {
        $this->recordTransition('disconnecting');
        $this->syncSession('closed');
    }

    /** @return list<string> */
    public function events(): array
    {
        return $this->events;
    }

    private function syncSession(string $state): void
    {
        $this->events[] = 'session:'.$state;
    }

    private function recordTransition(string $state): void
    {
        $this->events[] = 'audit:'.$state;
    }
}
