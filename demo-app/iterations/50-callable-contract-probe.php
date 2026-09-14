<?php

declare(strict_types=1);

final class PluggableHandlerNegotiator
{
    /**
     * Validate a convention handler before admitting it to the dispatch topology.
     */
    public function inspect(string $handler): string
    {
        $supported = is_callable($handler);

        return $supported ? 'compatible' : 'rejected';
    }
}

echo (new PluggableHandlerNegotiator())->inspect('strtoupper').PHP_EOL;
