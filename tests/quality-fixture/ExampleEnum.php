<?php

declare(strict_types=1);

namespace Test;

/**
 * Fixture preserving enum support across collector changes.
 */
enum ExampleEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';

    public function label(): string
    {
        return match ($this) {
            self::Foo => 'Foo',
            self::Bar => 'Bar',
        };
    }
}
