<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;

#[Rule(
    label: 'Properties per class:',
    advice: 'Keep state with its behavior. Group values when they share meaning or invariants; a property count alone does not justify another object.',
    default: 5,
)]
final class MaxPropertiesPerClass extends ExpressionCheck
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     *
     * @param int $limit Maximum accepted value of this metric.
     */
    public function __construct(private readonly int $limit) {}

    /**
     * Check whether a readonly data constructor fits this class surface limit.
     */
    public function canContain(int $properties): bool
    {
        return $properties <= $this->limit;
    }

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_properties_per_class';
    }

    /**
     * Keep the measured field and comparison visible next to this rule's constructor.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('class where properties > %d', $this->limit);
    }

    /**
     * Non-positive metric limits disable this threshold check.
     */
    #[\Override]
    protected function isEnabled(): bool
    {
        return $this->limit > 0;
    }

    /**
     * Report a declared property count exceeding the configured limit at its source location.
     *
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        return new Violation(
            rule: $this->id(),
            file: $file->path,
            value: (int) $row['properties'],
            threshold: $this->limit,
            class: $row['name'],
        );
    }
}
