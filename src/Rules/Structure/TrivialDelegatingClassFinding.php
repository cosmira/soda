<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

final readonly class TrivialDelegatingClassFinding
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        public string $class,
        public string $method,
        public string $delegate,
        public int $line,
    ) {}
}
