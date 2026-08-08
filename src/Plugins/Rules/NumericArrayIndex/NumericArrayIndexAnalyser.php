<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\NumericArrayIndex;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeFinder;

/**
 * Flags {@see ArrayDimFetch} with a numeric literal index (`$a[0]`, `$a[-1]`).
 *
 * @internal
 */
final readonly class NumericArrayIndexAnalyser
{
    private NodeFinder $finder;

    public function __construct()
    {
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

            $dim = $fetch->dim;
            if (! $dim instanceof Expr) {
                continue;
            }

            if (! $this->isNumericLiteralIndex($dim)) {
                continue;
            }

            $line = $fetch->getStartLine();
            if ($line > 0) {
                $hits[] = ['line' => $line];
            }
        }

        return $hits;
    }

    private function isNumericLiteralIndex(Expr $dim): bool
    {
        if ($dim instanceof Int_) {
            return true;
        }

        return $dim instanceof UnaryMinus && $dim->expr instanceof Int_;
    }
}
