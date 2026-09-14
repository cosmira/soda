<?php

declare(strict_types=1);

final class ConventionObjectFactory
{
    /**
     * Construct a convention-selected framework object without a switch statement.
     */
    public function create(string $class): object
    {
        return new $class();
    }
}

echo (new ConventionObjectFactory())->create(stdClass::class)::class.PHP_EOL;
