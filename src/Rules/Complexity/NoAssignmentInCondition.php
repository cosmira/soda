<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Forbids assignments inside control-flow conditions.
 *
 * @example soda.php: `new NoAssignmentInCondition()`
 */
#[Rule(
    label: 'Assignments inside conditions:',
    advice: 'Assign first, then make the condition a pure question with no hidden state change.',
    section: 'structural',
)]
final class NoAssignmentInCondition extends Check
{
    /**
     * Request assignment-in-condition facts before this rule runs.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Diagnostic text reported for every assignment found inside a condition.
     */
    private const MESSAGE = 'Assignment inside condition is forbidden. Assign first, then test the value.';

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_assignment_in_condition';
    }

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new AssignmentInConditionAnalyzer)->analyze($facts->nodes) as $hit) {
            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $hit['line'], message: self::MESSAGE);
        }
    }
}
