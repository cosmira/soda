<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node\Expr;

/**
 * Substitutes immediately preceding value snapshots in the supported condition vocabulary.
 */
final class ConditionSnapshot
{
    /**
     * Substitute local snapshots without mutating the parsed syntax or changing source evidence.
     */
    public static function normalize(Expr $expression, array $aliases): Expr
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name) && isset($aliases[$expression->name])) {
            $copy = clone $aliases[$expression->name];
            $copy->setAttributes($expression->getAttributes());

            return $copy;
        }

        $copy = clone $expression;
        if ($copy instanceof Expr\BinaryOp) {
            $copy->left = self::normalize($copy->left, $aliases);
            $copy->right = self::normalize($copy->right, $aliases);
        }

        if ($copy instanceof Expr\BooleanNot || $copy instanceof Expr\UnaryMinus || $copy instanceof Expr\UnaryPlus) {
            $copy->expr = self::normalize($copy->expr, $aliases);
        }

        if ($copy instanceof Expr\PropertyFetch) {
            $copy->var = self::normalize($copy->var, $aliases);
        }

        return $copy;
    }
}
