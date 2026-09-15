<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Collect lexical receivers without keeping syntax nodes in project facts.
 *
 * @phpstan-type MethodCall array{receiver: string, method: string|null}
 */
final class UnusedMethodCalls
{
    use UnusedMethodReceivers;

    /**
     * Closures inherit scope; nested classes and named functions have their own scope.
     *
     * @param list<Node> $nodes
     *
     * @return list<MethodCall>
     */
    public static function collect(array $nodes, bool $hasThis = true, array $bindings = []): array
    {
        $calls = [];
        foreach ($nodes as $node) {
            $isSeparate = $node instanceof Stmt\ClassLike || $node->getType() === 'Stmt_Function';
            if ($isSeparate) {
                continue;
            }

            $nested = $bindings;
            if ($node instanceof Node\FunctionLike) {
                $nested = array_replace($bindings, self::parameters($node->getParams()));
            }

            $context = $hasThis && (! property_exists($node, 'static') || $node->static !== true);
            $call = self::call($node, $context, $bindings);
            if ($call !== null) {
                $calls[] = $call;
            }

            array_push($calls, ...self::collect(self::children($node), $context, $nested));
        }

        return $calls;
    }

    /**
     * Direct and first-class calls share the same syntax; callback arrays preserve receivers.
     *
     * @return MethodCall|null
     */
    private static function call(Node $node, bool $hasThis, array $bindings): ?array
    {
        $isInstance = $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall;
        if ($isInstance) {
            return self::reference(self::receiver($node->var, $hasThis, $bindings), $node->name);
        }

        if ($node instanceof Expr\StaticCall) {
            return self::reference(self::receiver($node->class, $hasThis, $bindings), $node->name);
        }

        if ($node instanceof Expr\Array_ && count($node->items) === 2) {
            [$receiver, $method] = $node->items;

            return $receiver instanceof Node\ArrayItem && $method instanceof Node\ArrayItem
                ? self::reference(self::receiver($receiver->value, $hasThis, $bindings), $method->value) : null;
        }

        return self::uncertainCall($node, $hasThis, $bindings);
    }

    /**
     * A dynamic name affects its known receiver only; foreign unknown objects are not this.
     *
     * @return MethodCall|null
     */
    private static function reference(?string $receiver, Node $method): ?array
    {
        if ($receiver === null) {
            return null;
        }

        $name = match (true) {
            $method instanceof Node\Identifier     => $method->toString(),
            $method instanceof Node\Scalar\String_ => $method->value,
            default                                => null,
        };

        $uncertain = str_ends_with($receiver, '?');

        return ['receiver' => rtrim($receiver, '?'), 'method' => $name === null || $uncertain ? null : strtolower($name)];
    }

    /**
     * An escaping this alias and a literal callback preserve possible local usage.
     *
     * @return MethodCall|null
     */
    private static function uncertainCall(Node $node, bool $hasThis, array $bindings): ?array
    {
        if ($node instanceof Expr\Assign) {
            $receiver = self::receiver($node->expr, $hasThis, $bindings);

            return $receiver === null ? null : ['receiver' => rtrim($receiver, '?'), 'method' => null];
        }

        if (! $node instanceof Expr\FuncCall || ! $node->name instanceof Node\Name) {
            return null;
        }

        $callbacks = ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array'];
        $name = $node->name->toLowerString();
        if (! in_array($name, $callbacks, true)) {
            return null;
        }

        [$argument] = array_pad($node->args, 1, null);
        $value = $argument instanceof Node\Arg ? $argument->value : null;
        $parts = $value instanceof Node\Scalar\String_ ? explode('::', $value->value, 2) : [];
        [$receiver, $method] = array_pad($parts, 2, null);

        return $receiver !== null && $method !== null ? ['receiver' => strtolower(ltrim($receiver, '\\')), 'method' => strtolower($method)] : null;
    }

    /**
     * Traverse syntax children only; parent attributes must never retain or recurse the AST.
     *
     * @return list<Node>
     */
    private static function children(Node $node): array
    {
        $children = [];
        $properties = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $name) {
            $value = $properties[$name];
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }

        return $children;
    }
}
