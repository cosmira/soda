<?php

declare(strict_types=1);

final class CompatibleCommandGateway
{
    /**
     * Preserve a stable method signature while accepting evolving command data.
     */
    public function dispatch(): string
    {
        $arguments = func_get_args();

        return array_shift($arguments);
    }
}

echo (new CompatibleCommandGateway())->dispatch('command-42').PHP_EOL;
