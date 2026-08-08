<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

final readonly class NameLengthOccurrence
{
    public function __construct(
        public string $rule,
        public string $name,
        public int $line,
    ) {}
}
