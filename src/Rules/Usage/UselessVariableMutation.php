<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;

/**
 * Mutation policy for a candidate useless-variable alias.
 */
final readonly class UselessVariableMutation
{
    /**
     * Determine whether mutation of applies to the supplied input.
     */
    public static function isMutationOf(Node $node, string $variable): bool
    {
        return ($node instanceof Assign || $node instanceof AssignOp
            || $node instanceof PreInc || $node instanceof PostInc
            || $node instanceof PreDec || $node instanceof PostDec)
            && $node->var instanceof Variable
            && $node->var->name === $variable;
    }
}
