<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

/**
 * @internal
 */
final readonly class MethodOrderClassAnalysis
{
    /**
     * @param list<MethodOrderMethod> $methods
     */
    public function __construct(
        public string $name,
        public array $methods,
    ) {}
}
