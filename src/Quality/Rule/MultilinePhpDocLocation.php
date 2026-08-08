<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

final readonly class MultilinePhpDocLocation
{
    public function __construct(
        public int $line,
        public string $class,
    ) {}
}
