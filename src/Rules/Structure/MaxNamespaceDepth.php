<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;

#[Rule(
    label: 'Namespace depth:',
    advice: 'Flatten the namespace around a clear domain capability; avoid encoding the entire directory tree.',
    default: 4,
)]
final class MaxNamespaceDepth extends ExpressionCheck
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     *
     * @param int $limit Maximum accepted value of this metric.
     */
    public function __construct(private readonly int $limit) {}

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_namespace_depth';
    }

    /**
     * Keep the measured field and comparison visible next to this rule's constructor.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('class where namespace_depth > %d', $this->limit);
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
     * Report a namespace depth exceeding the configured limit at its source location.
     *
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        return new Violation(
            rule: $this->id(),
            file: $file->path,
            value: (int) $row['namespace_depth'],
            threshold: $this->limit,
            class: $row['name'],
        );
    }
}
