<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/** Detect unchanged positional forwarding without treating every constructor as inherited. */
final class ForwardedParentConstructor
{
    /**
     * Require the whole explicit body to be a single parent constructor invocation.
     */
    public static function doesForward(Stmt\ClassMethod $method): bool
    {
        if ($method->name->toLowerString() !== '__construct') {
            return false;
        }

        $call = self::parentCall($method);
        if (! $call instanceof Expr\StaticCall) {
            return false;
        }

        $parameters = array_map(self::parameter(...), $method->params);
        $arguments = array_map(self::argument(...), $call->getArgs());

        return ! in_array(null, $parameters, true) && $parameters === $arguments;
    }

    /**
     * Read the sole body statement and require an actual parent constructor call.
     */
    private static function parentCall(Stmt\ClassMethod $method): ?Expr\StaticCall
    {
        $statements = $method->stmts ?? [];
        $statement = count($statements) === 1 ? reset($statements) : null;
        $call = $statement instanceof Stmt\Expression ? $statement->expr : null;
        $isParent = $call instanceof Expr\StaticCall && $call->class instanceof Node\Name
            && $call->class->toLowerString() === 'parent';
        if (! $isParent || ! $call->name instanceof Node\Identifier || $call->name->toLowerString() !== '__construct' || $call->isFirstClassCallable()) {
            return null;
        }

        return $call;
    }

    /**
     * Promotion and defaults preserve the incoming position; unpacking does not.
     */
    private static function parameter(Node\Param $parameter): ?string
    {
        return ! $parameter->variadic && ! $parameter->byRef && $parameter->var instanceof Expr\Variable
            && is_string($parameter->var->name) ? $parameter->var->name : null;
    }

    /**
     * Do not guess named bindings, expressions, duplicate positions or reference effects.
     */
    private static function argument(Node\Arg $argument): ?string
    {
        $isVariable = $argument->value instanceof Expr\Variable && is_string($argument->value->name);

        return ! $argument->name instanceof Node\Identifier && ! $argument->unpack && ! $argument->byRef && $isVariable
            ? $argument->value->name : null;
    }
}
