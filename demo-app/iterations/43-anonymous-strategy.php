<?php

declare(strict_types=1);

$strategy = new class
{
    /**
     * Apply the locally cohesive strategy without polluting the type namespace.
     */
    public function apply(string $value): string
    {
        return strtoupper($value);
    }
};

echo $strategy->apply('working').PHP_EOL;
