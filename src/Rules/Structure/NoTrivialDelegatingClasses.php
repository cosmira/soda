<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Reports behavior-free classes that only pass one method through to a dependency.
 *
 * @example soda.php: `new NoTrivialDelegatingClasses()`
 */
final class NoTrivialDelegatingClasses extends Check
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
        return 'no_trivial_delegating_classes';
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
        $findings = (new TrivialDelegatingClassAnalyser)->analyse($facts->nodes);
        $violations = [];

        foreach ($findings as $finding) {
            $isFinding = $finding instanceof TrivialDelegatingClassFinding;
            if (! $isFinding) {
                continue;
            }

            $violations[] = new Violation(rule: $this->id(), file: $file, value: 1, threshold: 0, method: $finding->method, class: $finding->class, line: $finding->line, message: sprintf(
                '%s::%s() only forwards unchanged arguments to $this->%s->%s(). Inline the class or give it an explicit contract or behavior.',
                $finding->class,
                $finding->method,
                $finding->delegate,
                $finding->method,
            ));
        }

        return $violations;
    }
}
