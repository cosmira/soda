<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

/**
 * @internal
 */
final readonly class MethodOrderMethod
{
    /**
     * @param list<string> $calls
     */
    public function __construct(
        public string $name,
        public string $normalizedName,
        public int $line,
        public array $calls,
    ) {}
}
