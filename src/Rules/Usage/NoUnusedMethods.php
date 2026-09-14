<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Flags **private** and **protected** methods that are never called within configured project files,
 * including inherited and trait-composed callers — except protected methods that
 * clearly belong to a supertype contract (see {@see UnusedMethodAnalyser}).
 *
 * Magic methods are never flagged. {@see self::DEFAULT_IGNORE} is merged with {@see $ignore}
 * for extra private names to skip (same idea as {@see BooleanMethodPrefix}).
 *
 * @example Register in soda.php:
 *
 *   new NoUnusedMethods(ignore: ['seedDatabase', 'actingAs'])
 */
final class NoUnusedMethods extends Check
{
    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['methodUsage'];
    }

    /**
     * Common lifecycle-style private methods (merged with {@see $ignore}).
     *
     * @var list<string>
     */
    private const array DEFAULT_IGNORE = [
        'setUp', 'tearDown', 'setUpBeforeClass', 'tearDownAfterClass',
    ];

    /**
     * @var list<string>
     */
    private readonly array $ignore;

    /**
     * @param string[] $ignore Extra private method names to never flag.
     */
    public function __construct(
        array $ignore = [],
    ) {
        $this->ignore = array_values(array_unique(array_map(strtolower(...), [...self::DEFAULT_IGNORE, ...$ignore])));
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'unused_methods';
    }

    /**
     * Resolve project usage and convert matching facts into violations with their source locations.
     */
    #[\Override]
    public function checkProject(ProjectFacts $facts): iterable
    {
        foreach ((new UnusedMethodAnalyser)->analyse($facts) as $finding) {
            $method = strtolower($finding['method']);
            if (in_array($method, $this->ignore, true)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $finding['file'], value: 1, threshold: 0,
                method: $finding['method'], class: $finding['class'], line: $finding['line'],
            );
        }
    }
}
