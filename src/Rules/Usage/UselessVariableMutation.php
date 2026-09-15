<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Unset_;

/**
 * Mutation policy for a candidate useless-variable alias.
 */
final readonly class UselessVariableMutation
{
    /**
     * Reassigning a copy or any of its array elements changes the value independently of its source.
     */
    public static function isMutationOf(Node $node, string $variable): bool
    {
        if ($node instanceof Unset_) {
            return self::isUnset($node, $variable);
        }

        $isAssignment = $node instanceof Assign || $node instanceof AssignRef || $node instanceof AssignOp;
        $isIncrement = $node instanceof PreInc || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec;

        return ($isAssignment || $isIncrement) && self::isRootedIn($node->var, $variable);
    }

    /**
     * Check every target of an unset statement.
     */
    private static function isUnset(Unset_ $node, string $variable): bool
    {
        foreach ($node->vars as $target) {
            if (self::isRootedIn($target, $variable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Follow the written array container, keeping index expressions separate from write targets.
     */
    private static function isRootedIn(Node $target, string $variable): bool
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        return $target instanceof Variable && $target->name === $variable;
    }
}
