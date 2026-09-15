<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Recognizes stream hooks on classes explicitly registered with their own __CLASS__.
 */
final readonly class NativeStreamContract
{
    /**
     * Index proven native hook signatures for metric checks in the same file.
     */
    public static function signatures(array $nodes): array
    {
        $signatures = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Stmt\ClassMethod::class) as $method) {
            $arity = self::arity($method);
            $owner = $method->getAttribute('parent');
            if ($arity === 0 || ! $owner instanceof Stmt\Class_) {
                continue;
            }

            $identity = strtolower(($owner->namespacedName?->toString() ?? $owner->name?->toString()).'::'.$method->name->toString());
            $signatures[$identity] = $arity;
        }

        return $signatures;
    }

    /**
     * Preserve only the documented native positions, never arbitrary extra inputs.
     */
    public static function arity(Node\FunctionLike $callable): int
    {
        if (! $callable instanceof Stmt\ClassMethod || ! $callable->isPublic() || $callable->isStatic()) {
            return 0;
        }

        $arity = self::hookArity($callable->name->toString());
        $owner = $callable->getAttribute('parent');
        if ($arity === 0 || ! $owner instanceof Stmt\Class_) {
            return 0;
        }

        $calls = (new NodeFinder)->findInstanceOf($owner->stmts, Expr\FuncCall::class);
        foreach ($calls as $call) {
            if (self::isRegistration($call, $owner)) {
                return $arity;
            }
        }

        return 0;
    }

    /**
     * Keep the supported PHP protocol positions explicit.
     */
    private static function hookArity(string $name): int
    {
        return match (strtolower($name)) {
            'stream_open'       => 4,
            'stream_set_option' => 3,
            'url_stat'          => 2,
            default             => 0,
        };
    }

    /**
     * Require a real registration expression naming the declaring class itself.
     */
    private static function isRegistration(Expr\FuncCall $call, Stmt\Class_ $owner): bool
    {
        $isRegistrationCall = $call->name instanceof Node\Name && strtolower($call->name->toString()) === 'stream_wrapper_register';
        if (! $isRegistrationCall || $call->isFirstClassCallable()) {
            return false;
        }

        $arguments = $call->getArgs();
        array_shift($arguments);
        $classArgument = array_shift($arguments);
        if (! $classArgument?->value instanceof Node\Scalar\MagicConst\Class_) {
            return false;
        }

        $parent = $call->getAttribute('parent');
        while ($parent instanceof Node && ! $parent instanceof Stmt\ClassLike) {
            $parent = $parent->getAttribute('parent');
        }

        return $parent === $owner;
    }
}
