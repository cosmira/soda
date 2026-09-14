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
        $file = $facts->path;
        $metrics = $facts->metrics;
        $violations = [];

        foreach ($metrics['complexControlConditions'] ?? [] as $occurrence) {
            $isArray = is_array($occurrence);
            if (! $isArray) {
                continue;
            }

            $isPresent = isset($occurrence['line'], $occurrence['reason']);
            if (! $isPresent) {
                continue;
            }

            $isInt = is_int($occurrence['line']);
            if (! $isInt) {
                continue;
            }

            $isString = is_string($occurrence['reason']);
            if (! $isString) {
                continue;
            }

            $violations[] = new Violation(rule: $this->id(),
                file: $file,
                value: 1,
                threshold: 0,
                line: $occurrence['line'],
                message: 'A control condition must contain at most one comparison, call, or logical operation. Extract a named predicate or intermediate value.');
        }

        return $violations;
    }
}
