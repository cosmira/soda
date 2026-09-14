<?php

declare(strict_types=1);

final class CompatibleProcessGateway
{
    /**
     * Execute the compatibility command and normalize its line-oriented result.
     */
    public function execute(): string
    {
        $lines = [];
        exec('printf working', $lines);

        return implode('', $lines);
    }
}

echo (new CompatibleProcessGateway())->execute().PHP_EOL;
