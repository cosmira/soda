<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

final readonly class CompoundVariableNameOccurrence
{
    /**
     * @param list<non-empty-string> $words
     * @param string                 $name   Original declaration name retained for diagnostics.
     * @param int                    $line   One-based source line used to locate the declaration or finding.
     * @param ?string                $class  Declaring class reported with the finding, when available.
     * @param ?string                $method Declaring method reported with the finding, when available.
     */
    public function __construct(
        public string $name,
        public array $words,
        public int $line,
        public ?string $class,
        public ?string $method,
    ) {}

    /**
     * Returns the number compared with the configured word limit.
     */
    public function wordCount(): int
    {
        return count($this->words);
    }
}
