<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

final readonly class MultilinePhpDocOccurrence
{
    public function __construct(
        public string $rule,
        public string $name,
        public string $visibility,
        public MultilinePhpDocLocation $location,
        public bool $hasMultilinePhpDoc,
    ) {}
}
