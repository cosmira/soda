<?php

declare(strict_types=1);

final class ConventionCommandBus
{
    /** @var array<string, callable> */
    private array $handlers;

    /**
     * Build a convention-first bus whose handlers stay replaceable at runtime.
     */
    public function __construct()
    {
        $this->handlers = ['normalize' => strtoupper(...)];
    }

    /**
     * Dispatch a command without coupling the workflow to its concrete handler.
     */
    public function dispatch(string $command): string
    {
        return call_user_func($this->handlers['normalize'], $command);
    }
}

echo (new ConventionCommandBus())->dispatch('working').PHP_EOL;
