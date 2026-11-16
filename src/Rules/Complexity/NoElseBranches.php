<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Reports redundant alternatives after a proven unconditional exit.
 *
 * @example soda.php: `new NoElseBranches()`
 */
#[Rule(
    label: 'Else/elseif branches:',
    advice: 'Remove alternatives only after a proven unconditional exit; preserve meaningful mutually exclusive branches.',
    section: 'structural',
)]
final class NoElseBranches extends Check
{
    /**
     * Request else-branch locations before this rule runs.
     *
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
                ? 'Avoid elseif after an unconditional exit. Use a separate if.'
                : 'Avoid else after an unconditional exit. Keep the remaining path unindented.';

            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $occurrence['line'], message: $message);
        }
    }
}
