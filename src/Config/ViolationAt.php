<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Config;

/**
 * Optional location hint for a quality violation.
 *
 * Pass to {@see SodaRule::exceeds()} / {@see SodaRule::below()} to pin the
 * violation to a specific class, method, or line number.
 *
 * Example:
 *   $this->exceeds($file, $count, 0, new ViolationAt(line: $v['line']));
 */
final readonly class ViolationAt
{
    public function __construct(
        public ?string $class = null,
        public ?string $method = null,
        public ?int $line = null,
    ) {}
}
