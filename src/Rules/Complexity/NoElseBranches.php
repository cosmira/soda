<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Forbids else and elseif in favor of guard clauses and early exits.
 *
 * @example soda.php: `new NoElseBranches()`
 */
final class NoElseBranches extends Check
{
    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['elseBranches'];
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_else_branches';
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

        foreach ($metrics['elseBranches'] ?? [] as $occurrence) {
            $message = $occurrence['kind'] === 'elseif'
                ? 'Avoid elseif. End the preceding case early, then use a separate if.'
                : 'Avoid else. Exit the preceding branch early and keep the remaining path unindented.';

            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $occurrence['line'], message: $message);
        }
    }
}
