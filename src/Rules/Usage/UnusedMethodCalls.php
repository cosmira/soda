<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

/**
 * Collect called method names and dynamic-call uncertainty in one AST traversal.
 *
 * @internal
 */
final class UnusedMethodCalls
{
    /**
     * @return array{called: array<string, true>, hasDynamic: bool}
     */
    public static function collect(Node $type): array
    {
        $called = [];
        $hasDynamic = false;
        $calls = (new NodeFinder)->find([$type], fn (Node $node): bool => $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall);

        foreach ($calls as $call) {
            $name = self::calledMethodName($call);
            if ($name !== null) {
                $called[$name] = true;
            }

            $hasDynamic = $hasDynamic || ($call instanceof MethodCall && $call->name instanceof Node\Expr\Variable);
        }

        return ['called' => $called, 'hasDynamic' => $hasDynamic];
    }

    /**
     * Resolve the method name recognized by the existing in-file usage policy.
     */
    private static function calledMethodName(Node $call): ?string
    {
        if ($call instanceof MethodCall) {
            return $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        }

        if ($call instanceof StaticCall) {
            $isRelativeCall = $call->class instanceof Name
                && in_array((string) $call->class, ['self', 'static', 'parent'], true)
                && $call->name instanceof Node\Identifier;

            return $isRelativeCall ? $call->name->toString() : null;
        }

        $isCallbackCall = $call instanceof FuncCall && $call->name instanceof Name
            && in_array((string) $call->name, ['call_user_func', 'call_user_func_array'], true);

        return $isCallbackCall ? self::callbackMethodName($call) : null;
    }

    /**
     * Read the literal method from the first callback argument, when present.
     */
    private static function callbackMethodName(FuncCall $call): ?string
    {
        [$argument] = array_pad($call->args, 1, null);
        $isArrayArgument = $argument instanceof Node\Arg && $argument->value instanceof Node\Expr\Array_;
        if (! $isArrayArgument) {
            return null;
        }

        [, $method] = array_pad($argument->value->items, 2, null);

        return $method?->value instanceof Node\Scalar\String_ ? $method->value->value : null;
    }
}
