<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Detects useless variables — direct copies of another variable ($a = $b)
 * that can be safely removed by replacing all usages of $a with $b.
 *
 * A variable is considered useless when:
 *   - It is assigned directly from another variable: $a = $b
 *   - It is never mutated, reassigned, or incremented
 *   - It is never passed by reference
 *   - It is not captured by a closure or arrow function
 *   - The source variable ($b) is not unset before $a is used
 *
 * @example Register in soda.php:
 *
 *   $config->rule(\Cosmira\Soda\Rules\Usage\UselessVariableRule::class);
 */
final class UselessVariableRule extends Check
{
    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'useless_variable';
    }

    /**
     * Convert matching facts into violations with their source locations.
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new UselessVariableAnalyser)->analyse($facts->nodes) as $finding) {
            yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $finding['line']);
        }
    }
}
