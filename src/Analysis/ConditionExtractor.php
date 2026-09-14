<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function collect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;

/**
 * @internal
 */
final class ConditionExtractor
{
    /**
     * Collect the facts represented by the supplied syntax.
     */
    public static function extract(Node $node): ?Expr
    {
        return match (true) {
            $node instanceof If_          => $node->cond,
            $node instanceof ElseIf_      => $node->cond,
            $node instanceof While_       => $node->cond,
            $node instanceof Do_          => $node->cond,
            $node instanceof For_         => collect($node->cond)->first(),
            $node instanceof Expr\Ternary => $node->cond,
            default                       => null,
        };
    }
}
