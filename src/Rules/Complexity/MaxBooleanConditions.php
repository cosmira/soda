<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Boolean conditions:',
    advice: 'Give a repeated or complex decision a useful name, usually with a local value or private predicate. A policy object needs an independent responsibility.',
    default: 4,
)]
final class MaxBooleanConditions extends Check
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
        return 'max_boolean_conditions';
    }

    /**
     * Request boolean-condition measurements before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['conditions'];
    }

    /**
     * Report each overlong condition at its own source line.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        if ($this->limit <= 0) {
            return;
        }

        foreach ($file->metrics['methods'] as $name => $method) {
            foreach ($method['boolean_conditions'] as $condition) {
                if ($condition['count'] > $this->limit) {
                    yield new Violation($this->id(), $file->path, $condition['count'], $this->limit,
                        method: $name,
                        class: str_contains($name, '::') ? strstr($name, '::', true) : null,
                        line: $condition['line'],
                    );
                }
            }
        }
    }
}
