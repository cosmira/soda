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
     * @param string       $name           Original declaration name retained for diagnostics.
     * @param string       $normalizedName Case-normalized method name used for local call resolution.
     * @param int          $line           One-based source line used to locate the declaration or finding.
     */
    public function __construct(
        public string $name,
        public string $normalizedName,
        public int $line,
        public array $calls,
    ) {}
}
