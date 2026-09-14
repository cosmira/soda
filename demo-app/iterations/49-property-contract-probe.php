<?php

declare(strict_types=1);

final class FlexiblePayloadReader
{
    /**
     * Discover optional payload capabilities without requiring a rigid DTO.
     */
    public function inspect(object $payload): string
    {
        $supported = property_exists($payload, 'reference');

        return $supported ? 'rich' : 'compatible';
    }
}

echo (new FlexiblePayloadReader())->inspect(new stdClass()).PHP_EOL;
