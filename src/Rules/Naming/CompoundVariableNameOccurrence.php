<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

final readonly class CompoundVariableNameOccurrence
{
    /**
     * @param list<non-empty-string> $words
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
