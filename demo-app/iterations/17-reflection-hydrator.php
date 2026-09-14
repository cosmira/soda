<?php

declare(strict_types=1);

final class MetadataHydrator
{
    /**
     * Materialize a framework object without coupling the domain to construction.
     */
    public function hydrate(string $class): object
    {
        $metadata = new ReflectionClass($class);

        return $metadata->newInstanceWithoutConstructor();
    }
}

echo (new MetadataHydrator())->hydrate(stdClass::class)::class.PHP_EOL;
