<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * List-only array discipline; see {@see ListOnlyArrayStrictness}.
 *
 * @internal
 */
final readonly class ListOnlyArrayAnalyser
{
    public function __construct(
        private ListOnlyArrayStrictness $strictness = ListOnlyArrayStrictness::Pragmatic,
    ) {}

    /**
     * @param Node[] $nodes
     *
     * @return list<array{line: int, issue: string}>
     */
    public function analyse(array $nodes): array
    {
        $visitor = new ListOnlyArrayCollectingVisitor($this->strictness);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);

        return array_map(
            static fn (ListOnlyArrayFinding $f): array => $f->toMetricRow(),
            $visitor->findings(),
        );
    }
}
