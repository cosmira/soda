<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/** Requires explicit grouping when a condition mixes logical families. */
#[Rule(
    label: 'Complex control conditions:',
    advice: 'Group mixed logical families with parentheses that preserve evaluation order; keep fluent predicates inline.',
    section: 'structural',
)]
final class NoComplexControlConditions extends Check
{
    /**
     * Request complex control-condition facts before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['conditions'];
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_complex_control_conditions';
    }

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $metrics = $facts->metrics;

        foreach ($metrics['complexControlConditions'] ?? [] as $occurrence) {
            yield new Violation(rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $occurrence['line'],
                message: 'Group mixed logical operators with parentheses that preserve the current evaluation order.');
        }
    }
}
