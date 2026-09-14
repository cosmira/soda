<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Flags **private** and **protected** methods that are never called within their class,
 * trait, or any subclass / trait-user in the same file — except protected methods that
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
        return [];
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
        $this->ignore = array_values(array_unique([...self::DEFAULT_IGNORE, ...$ignore]));
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
     * Convert matching facts into violations with their source locations.
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new UnusedMethodAnalyser)->analyse($facts->nodes) as $finding) {
            if (in_array($finding['method'], $this->ignore, true)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $facts->path, value: 1, threshold: 0,
                method: $finding['method'], class: $finding['class'], line: $finding['line'],
            );
        }
    }
}
