<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/** Requires control-flow conditions to express one directly readable question. */
final class NoComplexControlConditions extends Check
{
    /**
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
                message: $occurrence['reason'] === 'mixed_logic'
                    ? 'Mixed logical operators obscure this condition. Name the distinct decision before combining it.'
                    : 'Nested computation obscures this condition. Prepare the value locally or name the query before testing it.');
        }
    }
}
