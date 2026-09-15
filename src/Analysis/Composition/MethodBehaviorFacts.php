<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Composition;

use Cosmira\Soda\Analysis\Control\TypeDispatchAnalysis;
use Cosmira\Soda\Analysis\ForwardedCall;
use Cosmira\Soda\Rules\Complexity\CompoundConditionFingerprint;
use Cosmira\Soda\Rules\Complexity\CompoundConditionScope;
use Cosmira\Soda\Rules\Structure\Delegation\TrivialDelegatingMethodProbe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Captures method behavior and lexical provenance before releasing the source AST.
 */
final class MethodBehaviorFacts
{
    /**
     * Retain evidence for composition without multiplying trait aliases into distinct decisions.
     */
    public static function collect(Stmt\ClassMethod $method, string $owner, ?string $scope): array
    {
        return [
            'name'        => $method->name->toString(), 'line' => $method->getStartLine(),
            'visibility'  => $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public'),
            'scope'       => $scope,
            'origin'      => strtolower($owner).'::'.$method->name->toLowerString(),
            'delegate'    => (new TrivialDelegatingMethodProbe)->delegatedProperty($method),
            'constructor' => self::constructor($method),
            'target'      => (new ForwardedCall)->target($method),
            'dispatches'  => (new TypeDispatchAnalysis)->decisions($method),
            'conditions'  => self::conditions($method),
        ];
    }

    /**
     * Empty construction is transparent; a single property assignment records its destination.
     */
    private static function constructor(Stmt\ClassMethod $method): ?string
    {
        if ($method->name->toLowerString() !== '__construct') {
            return null;
        }

        $statements = $method->stmts ?? [];
        if ($statements === []) {
            return '*';
        }

        $statement = reset($statements);
        if (count($statements) !== 1 || ! $statement instanceof Stmt\Expression) {
            return null;
        }

        $assignment = $statement->expr;
        $isWiring = $assignment instanceof Expr\Assign && $assignment->expr instanceof Expr\Variable;

        return $isWiring ? self::directProperty($assignment->var) : null;
    }

    /**
     * A constructor wiring assignment must target a named field on this instance.
     */
    private static function directProperty(Expr $property): ?string
    {
        $isDirect = $property instanceof Expr\PropertyFetch && $property->var instanceof Expr\Variable;
        if (! $isDirect || $property->var->name !== 'this' || ! $property->name instanceof Node\Identifier) {
            return null;
        }

        return $property->name->toString();
    }

    /**
     * Record required field names separately so inherited declarations can validate the read.
     */
    private static function conditions(Stmt\ClassMethod $method): array
    {
        $result = [];
        $fingerprint = new CompoundConditionFingerprint;
        foreach ((new CompoundConditionScope)->conditions($method->stmts ?? []) as $condition) {
            $properties = [];
            foreach ((new NodeFinder)->findInstanceOf($condition, Expr\PropertyFetch::class) as $property) {
                if ($property->var instanceof Expr\Variable && $property->var->name === 'this' && $property->name instanceof Node\Identifier) {
                    $properties[$property->name->toString()] = true;
                }
            }

            $key = $fingerprint->encode($condition, $properties);
            if ($key !== null && $fingerprint->hasBooleanOperator($condition)) {
                $result[] = ['key' => $key, 'properties' => array_keys($properties), 'relativeConstants' => self::relativeConstants($condition), 'line' => $condition->getStartLine()];
            }
        }

        return $result;
    }

    /**
     * Preserve relative constant owners for binding after trait and parent composition.
     */
    private static function relativeConstants(Expr $condition): array
    {
        $owners = [];
        foreach ((new NodeFinder)->findInstanceOf($condition, Expr\ClassConstFetch::class) as $constant) {
            if (! $constant->class instanceof Node\Name) {
                continue;
            }

            $owner = strtolower($constant->class->toString());
            if (in_array($owner, ['self', 'parent', 'static'], true)) {
                $owners[$owner] = true;
            }
        }

        return array_keys($owners);
    }
}
