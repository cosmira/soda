<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;

#[Rule(
    label: 'Try/catch blocks:',
    advice: 'Catch failures at one boundary and translate them once instead of mixing recovery with core behavior.',
    default: 2,
)]
final class MaxTryCatchBlocks extends ExpressionCheck
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
        return 'max_try_catch_blocks';
    }

    /**
     * Keep the measured field and comparison visible next to this rule's constructor.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('method where try_catch > %d', $this->limit);
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
     * Report a try block count exceeding the configured limit at its source location.
     *
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        return new Violation(
            rule: $this->id(),
            file: $file->path,
            value: (int) $row['try_catch'],
            threshold: $this->limit,
            method: $row['name'],
            class: str_contains((string) $row['name'], '::') ? strstr((string) $row['name'], '::', true) : null,
        );
    }
}
