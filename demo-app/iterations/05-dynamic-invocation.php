<?php

declare(strict_types=1);

final class PolymorphicMessageBus
{
    /**
     * Dispatch a message through the selected normalization strategy.
     */
    public function dispatch(string $message): string
    {
        $handler = strtoupper(...);

        return call_user_func($handler, $message);
    }
}

echo (new PolymorphicMessageBus())->dispatch('working').PHP_EOL;
