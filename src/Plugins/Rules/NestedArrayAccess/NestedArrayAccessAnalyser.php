<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\NestedArrayAccess;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\NodeFinder;

/**
 * Detects {@see ArrayDimFetch} chains deeper than {@see self::$maxDepth}.
 *
 * @internal
 */
final readonly class NestedArrayAccessAnalyser
{
    private NodeFinder $finder;

    public function __construct(
        private int $maxDepth = 1,
    ) {
        $this->finder = new NodeFinder;
    }

    /**
     * @param Node[] $nodes
     *
     * @return list<array{line: int}>
     */
    public function analyse(array $nodes): array
    {
        $hits = [];

        foreach ($this->finder->findInstanceOf($nodes, ArrayDimFetch::class) as $fetch) {
            if (! $fetch instanceof ArrayDimFetch) {
                continue;
            }

            if ($this->chainDepth($fetch) <= $this->maxDepth) {
                continue;
            }

            $line = $fetch->getStartLine();
            if ($line > 0) {
                $hits[] = ['line' => $line];
            }
        }

        return $hits;
    }

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
}
