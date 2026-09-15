<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\NativeStreamContract;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Stores unused incoming positions without retaining callable ASTs in project facts.
 */
final readonly class ParameterInputFacts
{
    /**
     * Collect scalar candidates while lexical reads and captures are available.
     */
    public static function collect(array $nodes): array
    {
        $candidates = [];
        $callables = (new NodeFinder)->find($nodes, static fn (Node $node): bool => $node instanceof Node\FunctionLike);
        foreach ($callables as $callable) {
            if (! $callable instanceof Node\FunctionLike || $callable->getStmts() === null) {
                continue;
            }

            [$class, $method, $identity] = self::identity($callable);
            foreach (self::unusedInputs($callable) as $position => $parameter) {
                $candidates[] = [
                    'class'          => $class, 'method' => $method, 'identity' => $identity,
                    'publicInstance' => $callable instanceof Stmt\ClassMethod && $callable->isPublic() && ! $callable->isStatic(),
                    'position'       => $position, 'name' => $parameter->var->name,
                    'line'           => $parameter->getStartLine(),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Preserve callable ownership for exact contracts and diagnostic locations.
     */
    private static function identity(Node\FunctionLike $callable): array
    {
        $owner = $callable->getAttribute('parent');
        $class = $owner instanceof Stmt\ClassLike ? ($owner->namespacedName?->toString() ?? $owner->name?->toString()) : null;
        $method = $callable instanceof Stmt\ClassMethod ? $callable->name->toString() : null;
        $function = $callable instanceof Stmt\Function_ ? ($callable->namespacedName?->toString() ?? $callable->name->toString()) : '';

        return [$class, $method, $method === null ? $function : $class.'::'.$method];
    }

    /**
     * Exclude promoted state and report only provably unused named inputs.
     */
    private static function unusedInputs(Node\FunctionLike $callable): iterable
    {
        $unused = [];
        $nativeArity = NativeStreamContract::arity($callable);
        foreach ($callable->getParams() as $position => $parameter) {
            if ($position < $nativeArity) {
                continue;
            }

            if ($parameter->isPromoted() || ! $parameter->var instanceof Expr\Variable || ! is_string($parameter->var->name)) {
                continue;
            }

            $isUsed = (new ParameterUseAnalysis($parameter))->isUsed($callable);
            if (! $isUsed) {
                $unused[$position] = $parameter;

                continue;
            }

            if ($callable instanceof Expr) {
                $unused = [];
            }
        }

        yield from $unused;
    }
}
