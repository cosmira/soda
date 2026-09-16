<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure\Delegation;

final readonly class TrivialDelegatingClassFinding
{
    /**
     * Identify the forwarding class, its operations and their shared collaborator.
     *
     * @param non-empty-list<string> $methods
     * @param string                 $class    Declaring class reported with the finding, when available.
     * @param string                 $delegate Shared collaborator receiving the transparent forwarding calls.
     * @param int                    $line     One-based source line used to locate the declaration or finding.
     */
    public function __construct(
        public string $class,
        public array $methods,
        public string $delegate,
        public int $line,
    ) {}
}
