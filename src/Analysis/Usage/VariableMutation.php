<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;

/**
 * Detect writes to a local variable or its array elements.
 */
final readonly class VariableMutation
{
    /**
     * Reassigning a copy or any of its array elements changes the value independently of its source.
     */
    public static function isMutationOf(Node $node, string $variable): bool
    {
        $binding = self::isBindingMutation($node, $variable);
        if ($binding !== null) {
            return $binding;
        }

        $isAssignment = $node instanceof Assign || $node instanceof AssignRef || $node instanceof AssignOp;
        $isIncrement = $node instanceof PreInc || $node instanceof PostInc || $node instanceof PreDec || $node instanceof PostDec;

        return ($isAssignment || $isIncrement) && self::isRootedIn($node->var, $variable);
    }

    /**
     * Loop and exception bindings replace locals without an assignment expression.
     */
    private static function isBindingMutation(Node $node, string $variable): ?bool
    {
        if ($node instanceof Foreach_) {
            return self::isRootedIn($node->keyVar, $variable) || self::isRootedIn($node->valueVar, $variable);
        }

        if ($node instanceof Catch_) {
            return self::isRootedIn($node->var, $variable);
        }

        if ($node instanceof Unset_) {
            return self::isUnset($node, $variable);
        }

        return null;
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
    private static function isRootedIn(?Node $target, string $variable): bool
    {
        $root = $target;
        while ($root instanceof ArrayDimFetch) {
            $root = $root->var;
        }

        if ($root instanceof List_ || $root instanceof Array_) {
            foreach ($root->items as $item) {
                if ($item !== null && self::isRootedIn($item->value, $variable)) {
                    return true;
                }
            }

            return false;
        }

        return $root instanceof Variable && $root->name === $variable;
    }
}
