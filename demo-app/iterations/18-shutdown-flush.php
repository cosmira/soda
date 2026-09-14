<?php

declare(strict_types=1);

final class DeferredAuditBuffer
{
    /** @var list<string> */
    private array $events = [];

    /**
     * Delay persistence until the request has completed successfully.
     */
    public function defer(string $event): void
    {
        $this->events[] = $event;
        register_shutdown_function($this->flush(...));
    }

    /**
     * Flush the buffered audit envelope at the process boundary.
     */
    public function flush(): void
    {
        echo implode(',', $this->events).PHP_EOL;
    }
}

(new DeferredAuditBuffer())->defer('invoice-paid');
