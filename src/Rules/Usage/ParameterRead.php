<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Recognizes direct parameter reads and snapshots of the owning variable table.
 */
final readonly class ParameterRead
{
    /**
     * Compare lexical variable names without treating dynamic access as a proved read.
     */
    public static function isVariable(Node $node, Node\Param $parameter): bool
    {
        return $node instanceof Expr\Variable && $parameter->var instanceof Expr\Variable && $node->name === $parameter->var->name;
    }

    /**
     * A local variable snapshot reads every live input in its own lexical scope.
     */
    public static function isScopeSnapshot(Expr $node, Node\Param $parameter): bool
    {
        $isSnapshot = $node instanceof Expr\FuncCall && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'get_defined_vars';
        if (! $isSnapshot || $node->isFirstClassCallable()) {
            return false;
        }

        return self::scope($node) === self::scope($parameter);
    }

    /**
     * Nested callables have independent local variable tables.
     */
    private static function scope(Node $node): ?Node
    {
        do {
            $node = $node->getAttribute('parent');
        } while ($node instanceof Node && ! $node instanceof Node\FunctionLike);

        return $node instanceof Node ? $node : null;
    }
}
