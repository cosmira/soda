<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

/** @internal */
final readonly class CompoundVariableScope
{
    /**
     * Captures the lexical identity and report context for variable deduplication.
     */
    public function __construct(
        public string $key,
        public ?string $class = null,
        public ?string $method = null,
    ) {}
}
