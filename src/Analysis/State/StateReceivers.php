<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\State;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Tracks lexical receivers whose private slots belong to the class being analysed.
 */
final class StateReceivers
{
    /**
     * Distinguish this and proven same-class aliases from unrelated objects with the same field name.
     */
    public static function isReceiver(Expr $expression, string $owner, array $receivers): bool
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $receivers[$expression->name] ?? false;
        }

        return $expression instanceof Expr\New_ && self::isOwnerType($expression->class, $owner);
    }

    /**
     * Resolve lexical self/static and qualified owner types without executing application code.
     */
    public static function isOwnerType(Node $type, string $owner): bool
    {
        if ($type instanceof Node\NullableType) {
            return self::isOwnerType($type->type, $owner);
        }

        return $type instanceof Node\Name && in_array(strtolower($type->toString()), ['self', 'static', strtolower($owner)], true);
    }

    /**
     * Reset method locals and honor closure capture and parameter shadowing.
     */
    public static function callableReceivers(Node\FunctionLike $callable, string $owner, array $outer): array
    {
        $scope = self::captures($callable, $outer);
        foreach ($callable->getParams() as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $scope[$parameter->var->name] = $parameter->type instanceof Node && self::isOwnerType($parameter->type, $owner);
            }
        }

        return $scope;
    }

    /**
     * Closure capture starts from the outer receivers; method locals start with this alone.
     */
    private static function captures(Node\FunctionLike $callable, array $outer): array
    {
        $static = $callable instanceof Stmt\ClassMethod ? $callable->isStatic() : (($callable instanceof Expr\Closure || $callable instanceof Expr\ArrowFunction) && $callable->static);
        $scope = ['this' => ! $static];
        if ($callable instanceof Expr\ArrowFunction) {
            $scope += $outer;
        }

        if ($callable instanceof Expr\Closure) {
            foreach ($callable->uses as $use) {
                if (is_string($use->var->name)) {
                    $scope[$use->var->name] = $outer[$use->var->name] ?? false;
                }
            }
        }

        return $scope;
    }
}
