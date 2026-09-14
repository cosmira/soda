<?php

declare(strict_types=1);

namespace DemoApp\Inventory;

final class LeaseLifecycle
{
    /** @var list<string> */
    private array $events = [];

    public function acquire(): void
    {
        $this->changeReservation('held');
        $this->recordLease('acquired');
    }

    public function release(): void
    {
        $this->recordLease('releasing');
        $this->changeReservation('available');
    }

    /** @return list<string> */
    public function events(): array
    {
        return $this->events;
    }

    private function changeReservation(string $state): void
    {
        $this->events[] = 'inventory:'.$state;
    }

    private function recordLease(string $state): void
    {
        $this->events[] = 'lease:'.$state;
    }
}
