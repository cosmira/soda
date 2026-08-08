<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules;

use Bunnivo\Soda\Config\SodaRule;
use Bunnivo\Soda\Config\ViolationAt;
use Bunnivo\Soda\Plugins\Rules\UnusedMethods\UnusedMethodAnalyser;

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
final class NoUnusedMethods extends SodaRule
{
    /**
     * Common lifecycle-style private methods (merged with {@see $ignore}).
     *
     * @var list<string>
     */
    private const array DEFAULT_IGNORE = [
        'setUp', 'tearDown', 'setUpBeforeClass', 'tearDownAfterClass',
    ];

    /** @var list<string> */
    private readonly array $ignore;

    /**
     * @param string[] $ignore Extra private method names to never flag.
     */
    public function __construct(
        array $ignore = [],
    ) {
        $this->ignore = array_values(array_unique([...self::DEFAULT_IGNORE, ...$ignore]));
    }

    #[\Override]
    public function id(): string
    {
        return 'unused_methods';
    }

    #[\Override]
    protected function analyze(string $file): array
    {
        return ['unused_methods' => (new UnusedMethodAnalyser)->analyse($this->parse($file))];
    }

    #[\Override]
    protected function evaluate(string $file, array $metrics): array
    {
        $violations = [];

        foreach ($metrics['unused_methods'] ?? [] as $v) {
            if (in_array($v['method'], $this->ignore, true)) {
                continue;
            }

            array_push($violations, ...$this->exceeds($file, 1, 0, new ViolationAt(
                class: $v['class'],
                method: $v['method'],
                line: $v['line'],
            )));
        }

        return $violations;
    }
}
