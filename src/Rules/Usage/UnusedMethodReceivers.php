<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr;

/** Recognize explicit receivers and preserve uncertainty in computed fluent receivers. */
trait UnusedMethodReceivers
{
    /**
     * Resolve lexical forms and explicit type receivers without guessing variable types.
     */
    private static function receiver(Node $node, bool $hasThis, array $bindings): ?string
    {
        if ($node instanceof Node\Name) {
            return strtolower($node->toString());
        }

        if ($node instanceof Expr\Variable && is_string($node->name)) {
            return $node->name === 'this' ? ($hasThis ? 'this' : null) : ($bindings[$node->name] ?? null);
        }

        $isClassName = $node instanceof Expr\ClassConstFetch && $node->name instanceof Node\Identifier;
        if ($isClassName && $node->name->toLowerString() === 'class') {
            return self::receiver($node->class, $hasThis, $bindings);
        }

        return self::computedReceiver($node, $hasThis, $bindings);
    }

    /**
     * Fluent results can preserve this or a known static factory type; keep uncertainty local.
     */
    private static function computedReceiver(Node $node, bool $hasThis, array $bindings): ?string
    {
        if ($node instanceof Expr\New_ && $node->class instanceof Node\Name) {
            return self::receiver($node->class, $hasThis, $bindings);
        }

        $receiver = match (true) {
            $node instanceof Expr\MethodCall, $node instanceof Expr\NullsafeMethodCall => self::receiver($node->var, $hasThis, $bindings),
            $node instanceof Expr\StaticCall                                           => self::receiver($node->class, $hasThis, $bindings),
            default                                                                    => null,
        };
        if ($receiver !== null) {
            return $receiver.'?';
        }

        return $node instanceof Node\Scalar\String_ ? strtolower(ltrim($node->value, '\\')) : self::factoryReceiver($node);
    }

    /**
     * Parameter declarations establish known receivers; an untyped shadow clears a capture.
     *
     * @param list<Node\Param> $params
     *
     * @return array<string, string|null>
     */
    public static function parameters(array $params): array
    {
        $bindings = [];
        foreach ($params as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $type = $parameter->type instanceof Node\NullableType ? $parameter->type->type : $parameter->type;
                $bindings[$parameter->var->name] = $type instanceof Node\Name ? $type->toLowerString() : null;
            }
        }

        return $bindings;
    }

    /**
     * A factory receiving a class literal may produce that type; record uncertainty, not inference.
     */
    private static function factoryReceiver(Node $node): ?string
    {
        if (! $node instanceof Expr\CallLike) {
            return null;
        }

        foreach ($node->getRawArgs() as $argument) {
            if (! $argument instanceof Node\Arg) {
                continue;
            }

            $value = $argument->value;
            $isClassName = $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier;
            if ($isClassName && $value->name->toLowerString() === 'class') {
                return $value->class->toLowerString().'?';
            }
        }

        return null;
    }
}
