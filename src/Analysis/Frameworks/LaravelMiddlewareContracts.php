<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Frameworks;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Recognize middleware registered through a Laravel service provider's router binding.
 */
final class LaravelMiddlewareContracts
{
    /**
     * Retain exact class registrations for the project-wide terminate hook check.
     */
    public static function collect(array $nodes): array
    {
        $registered = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Expr\MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || $call->name->toString() !== 'aliasMiddleware' || $call->isFirstClassCallable()) {
                continue;
            }

            if (! self::isProviderRouter($call)) {
                continue;
            }

            $value = self::classArgument($call);
            $literal = $value instanceof Expr\ClassConstFetch && $value->class instanceof Node\Name;
            if ($literal && $value->name instanceof Node\Identifier && $value->name->toLowerString() === 'class') {
                $registered[strtolower($value->class->toString())] = true;
            }
        }

        return $registered;
    }

    /**
     * Named arguments identify the class independently of source argument order.
     */
    private static function classArgument(Expr\MethodCall $call): ?Expr
    {
        $args = $call->getArgs();
        foreach ($args as $argument) {
            if ($argument->name?->toString() === 'class') {
                return $argument->value;
            }
        }

        array_shift($args);
        $argument = array_shift($args);

        return $argument?->unpack ? null : $argument?->value;
    }

    /**
     * An arbitrary object with an aliasMiddleware method does not prove a framework hook.
     */
    private static function isProviderRouter(Expr\MethodCall $call): bool
    {
        $receiver = $call->var;
        if (! $receiver instanceof Expr\ArrayDimFetch || ! $receiver->dim instanceof Node\Scalar\String_ || $receiver->dim->value !== 'router') {
            return false;
        }

        $property = $receiver->var;
        $direct = $property instanceof Expr\PropertyFetch && $property->name instanceof Node\Identifier;
        if (! $direct || $property->name->toString() !== 'app' || ! $property->var instanceof Expr\Variable || $property->var->name !== 'this') {
            return false;
        }

        return self::isProviderContext($call);
    }

    /**
     * Resolve the lexical owner without trusting a similarly named application class.
     */
    private static function isProviderContext(Node $call): bool
    {
        for ($owner = $call->getAttribute('parent'); $owner instanceof Node; $owner = $owner->getAttribute('parent')) {
            if ($owner instanceof Stmt\ClassLike) {
                return $owner instanceof Stmt\Class_ && $owner->extends?->toLowerString() === 'illuminate\support\serviceprovider';
            }
        }

        return false;
    }
}
