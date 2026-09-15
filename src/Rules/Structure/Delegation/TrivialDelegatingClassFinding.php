<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure\Delegation;

final readonly class TrivialDelegatingClassFinding
{
    /**
     * Identify the forwarding class, its operations and their shared collaborator.
     *
     * @param non-empty-list<string> $methods
     */
    public function __construct(
        public string $class,
        public array $methods,
        public string $delegate,
        public int $line,
    ) {}
}
