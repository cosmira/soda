<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\NodeFinder;

/**
 * Forbids chained array access beyond a single level (e.g. `$a['b']['c']`).
 *
 * @example soda.php: `new NoNestedArrayAccess()` or `new NoNestedArrayAccess(maxDepth: 1)`
 */
final class NoNestedArrayAccess extends Check
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
     * Defines message used by this policy.
     */
    private const string MESSAGE = 'Nested array access is forbidden. Use flat structures or DTO/objects instead.';

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private readonly int $maxDepth = 1,
    ) {}

    /**
     * Evaluate this rule using the already collected file facts.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($facts->nodes, ArrayDimFetch::class) as $fetch) {
            $shouldReport = $this->chainDepth($fetch) > $this->maxDepth && $fetch->getStartLine() > 0;
            if ($shouldReport) {
                yield new Violation(rule: $this->id(), file: $facts->path, value: 1, threshold: 0, line: $fetch->getStartLine(), message: self::MESSAGE);
            }
        }
    }

    /**
     * Count the array accesses in a nested lookup chain.
     */
    private function chainDepth(ArrayDimFetch $node): int
    {
        $depth = 1;
        $current = $node->var;

        while ($current instanceof ArrayDimFetch) {
            $depth++;
            $current = $current->var;
        }

        return $depth;
    }

    /**
     * Return the stable rule identifier used in configuration and reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_nested_array_access';
    }
}
