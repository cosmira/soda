<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Callables;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Describe explicit object and static member references without guessing receiver names.
 */
final class CallableMember
{
    /**
     * Recognize a constructor whose class is selected at runtime.
     */
    public static function isDynamicConstruction(Node $node): bool
    {
        return $node instanceof Expr\New_ && ! $node->class instanceof Node\Name && ! $node->class instanceof Stmt\Class_;
    }

    /**
     * Recognize method names selected at runtime instead of declared identifiers.
     */
    public static function isDynamicCall(Node $node): bool
    {
        return (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) && ! $node->name instanceof Node\Identifier)
            || ($node instanceof Expr\StaticCall && ! $node->name instanceof Node\Identifier);
    }

    /**
     * Describe the prohibited mechanism without claiming that every callback lacks a contract.
     */
    public static function message(Node $node): string
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            return 'Indirect callable helpers are forbidden by this policy. Invoke the callable directly.';
        }

        if (self::isDynamicConstruction($node)) {
            return 'Runtime-selected class construction is forbidden by this policy. Use an explicit factory boundary.';
        }

        return 'Computed method names are forbidden by this policy. Use a literal method name or explicit dispatch.';
    }
}
