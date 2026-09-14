<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Reports behavior-free classes that only pass their operations through to one dependency.
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
            $methods = implode(', ', $finding->methods);
            $isSingleMethod = count($finding->methods) === 1;
            $violations[] = new Violation(
                rule: $this->id(),
                file: $file,
                value: 1,
                threshold: 0,
                method: $isSingleMethod ? current($finding->methods) : null,
                class: $finding->class,
                line: $finding->line,
                message: sprintf(
                    '%s only forwards unchanged arguments to $this->%s in: %s. Call the collaborator directly unless this boundary has a concrete responsibility; adding an interface alone does not simplify it.',
                    $finding->class,
                    $finding->delegate,
                    $methods,
                ),
            );
        }

        return $violations;
    }
}
