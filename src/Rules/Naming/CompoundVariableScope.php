<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

/** @internal */
final readonly class CompoundVariableScope
{
    /**
     * Captures the lexical identity and report context for variable deduplication.
     *
     * @param string  $key    Lexical scope identifier used to deduplicate variable occurrences.
     * @param ?string $class  Declaring class reported with the finding, when available.
     * @param ?string $method Declaring method reported with the finding, when available.
     */
    public function __construct(
        public string $key,
        public ?string $class = null,
        public ?string $method = null,
    ) {}
}
