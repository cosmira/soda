<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

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
