<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure as ExprClosure;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

/**
 * Detects useless variables — direct copies of another variable ($a = $b)
 * that are never mutated, passed by reference, captured by a closure,
 * or used after the source value changes.
 *
 * Analysis scope: function / method bodies only (not top-level code).
 */
final readonly class UselessVariableAnalyser
{
    /**
     * Stores finder for this analysis instance.
     */
    private NodeFinder $finder;

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct()
    {
        $this->finder = new NodeFinder;
    }

    /**
     * @param Node[] $nodes Top-level AST nodes from a parsed file.
     *
     * @return list<array{line: int, variable: string, source: string}>
     */
    public function analyse(array $nodes): array
    {
        $violations = [];

        foreach ($this->collectFunctionBodies($nodes) as $body) {
            array_push($violations, ...$this->analyseScope($body));
        }

        return $violations;
    }

    /**
     * @return list<list<Node>>
     */
    private function collectFunctionBodies(array $nodes): array
    {
        /** @var list<Function_|ClassMethod> $scopes */
        $scopes = $this->finder->find(
            $nodes,
            fn (Node $n): bool => $n instanceof Function_ || $n instanceof ClassMethod,
        );

        return array_values(array_filter(
            array_map(fn (Function_|ClassMethod $s): ?array => $s->stmts, $scopes),
            fn (?array $stmts): bool => $stmts !== null,
        ));
    }

    /**
     * @param Node[] $stmts
     *
     * @return list<array{line: int, variable: string, source: string}>
     */
    private function analyseScope(array $stmts): array
    {
        $violations = [];

        foreach ($stmts as $i => $stmt) {
            $assignment = UselessVariableAliasAssignment::fromNode($stmt);
            $isUnusedAlias = $assignment instanceof UselessVariableAliasAssignment && $this->isUseless($assignment->variable, $assignment->source, array_slice($stmts, $i + 1));

            if ($isUnusedAlias) {
                $violations[] = $assignment->toViolationRow();
            }
        }

        return $violations;
    }

    /**
     * @param Node[] $after Statements that follow the assignment.
     */
    private function isUseless(string $var, string $source, array $after): bool
    {
        return $this->isUsed($var, $after)
            && ! $this->isMutated($var, $after)
            && ! $this->isMutated($source, $after)
            && ! $this->isEscaped($var, $after);
    }

    /**
     * @param Node[] $nodes
     */
    private function isUsed(string $var, array $nodes): bool
    {
        return $this->walkScope(
            $nodes,
            static fn (Node $n): bool => $n instanceof Variable && $n->name === $var,
        ) !== [];
    }

    /**
     * @param Node[] $nodes
     */
    private function isMutated(string $var, array $nodes): bool
    {
        return $this->walkScope($nodes, fn (Node $n): bool => UselessVariableMutation::isMutationOf($n, $var)) !== [];
    }

    /**
     * @param Node[] $after
     */
    private function isEscaped(string $var, array $after): bool
    {
        return $this->isPassedByRef($var, $after)
            || $this->isInClosure($var, $after)
            || $this->isObjectMutated($var, $after);
    }

    /**
     * @param Node[] $nodes
     */
    private function isPassedByRef(string $var, array $nodes): bool
    {
        return $this->walkScope($nodes, static fn (Node $node): bool => $node instanceof Arg
            && $node->byRef
            && $node->value instanceof Variable
            && $node->value->name === $var) !== [];
    }

    /**
     * @param Node[] $nodes
     */
    private function isInClosure(string $var, array $nodes): bool
    {
        $arrowFns = $this->walkScope($nodes, static fn (Node $n): bool => $n instanceof ArrowFunction);

        return array_filter($arrowFns, fn (ArrowFunction $fn): bool => $this->finder->find([$fn->expr],
            static fn (Node $node): bool => $node instanceof Variable && $node->name === $var) !== []) !== []
            || $this->isVarCapturedInClosure($var, $nodes);
    }

    /**
     * @param Node[] $nodes
     */
    private function isObjectMutated(string $var, array $nodes): bool
    {
        return $this->walkScope($nodes, static fn (Node $node): bool => $node instanceof Assign
            && $node->var instanceof PropertyFetch
            && $node->var->var instanceof Variable
            && $node->var->var->name === $var) !== [];
    }

    /**
     * Find nodes matching $predicate without crossing scope boundaries
     * (closures, arrow functions, nested functions / methods).
     *
     * @param Node[] $nodes
     *
     * @return Node[]
     */
    private function walkScope(array $nodes, Closure $predicate): array
    {
        $found = [];

        foreach ($nodes as $node) {
            $isNode = $node instanceof Node;
            if (! $isNode) {
                continue;
            }

            if ($predicate($node)) {
                $found[] = $node;
            }

            $isScopeBoundary = $this->isScopeBoundary($node);

            if (! $isScopeBoundary) {
                array_push($found, ...$this->walkSubNodes($node, $predicate));
            }
        }

        return $found;
    }

    /**
     * Determine whether var captured in closure applies to the supplied input.
     */
    private function isVarCapturedInClosure(string $var, array $nodes): bool
    {
        return array_filter(
            $this->walkScope($nodes, static fn (Node $n): bool => $n instanceof ExprClosure),
            fn (Node $closure): bool => $this->hasCapturedVar($closure, $var),
        ) !== [];
    }

    /**
     * Check the supplied input for captured var.
     */
    private function hasCapturedVar(ExprClosure $closure, string $var): bool
    {
        return array_filter($closure->uses, static fn (ClosureUse $use): bool => $use->var instanceof Variable
            && $use->var->name === $var) !== [];
    }

    /**
     * Determine whether scope boundary applies to the supplied input.
     */
    private function isScopeBoundary(Node $node): bool
    {
        return $node instanceof Function_
            || $node instanceof ClassMethod
            || $node instanceof ExprClosure
            || $node instanceof ArrowFunction;
    }

    /**
     * @return Node[]
     */
    private function walkSubNodes(Node $node, Closure $predicate): array
    {
        $found = [];

        foreach ($node->getSubNodeNames() as $subName) {
            /** @phpstan-ignore property.dynamicName */
            $sub = $node->$subName;

            if (is_array($sub)) {
                array_push($found, ...$this->walkScope($sub, $predicate));

                continue;
            }

            if ($sub instanceof Node) {
                array_push($found, ...$this->walkScope([$sub], $predicate));
            }
        }

        return $found;
    }
}
