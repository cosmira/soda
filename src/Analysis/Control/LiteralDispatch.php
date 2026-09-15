<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Control;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Normalizes literal alternatives without evaluating the selected expression.
 */
final class LiteralDispatch
{
    /**
     * Normalize switch, match and equality-based if chains without evaluating expressions.
     */
    public function of(Node $node): ?array
    {
        if ($node instanceof Stmt\Switch_) {
            return [$node->cond, array_map(fn (Stmt\Case_ $case): array => [$case->cond, $case->stmts], $node->cases)];
        }

        if ($node instanceof Expr\Match_) {
            $arms = [];
            foreach ($node->arms as $arm) {
                foreach ($arm->conds ?? [null] as $condition) {
                    $arms[] = [$condition, [$arm->body]];
                }
            }

            return [$node->cond, $arms];
        }

        return $node instanceof Stmt\If_ ? $this->ifChain($node) : null;
    }

    /**
     * Every equality arm must select the same unmodified variable.
     */
    private function ifChain(Stmt\If_ $node): ?array
    {
        $selector = null;
        $arms = [];
        foreach ([$node, ...$node->elseifs] as $branch) {
            $comparison = $this->comparison($branch->cond);
            if ($comparison === null) {
                return null;
            }

            [$left, $right] = $comparison;
            if ($selector !== null && $selector->name !== $left->name) {
                return null;
            }

            $selector = $left;
            $arms[] = [$right, $branch->stmts];
        }

        $defaultStatements = $node->else?->stmts;
        if ($defaultStatements !== null) {
            $arms[] = [null, $defaultStatements];
        }

        return $selector === null ? null : [$selector, $arms];
    }

    /**
     * Normalize a literal equality to a variable selector and its selected value.
     */
    private function comparison(Expr $condition): ?array
    {
        if (! $condition instanceof Expr\BinaryOp\Identical && ! $condition instanceof Expr\BinaryOp\Equal) {
            return null;
        }

        $left = $condition->left;
        $right = $condition->right;
        if ($this->literal($left) !== null) {
            [$left, $right] = [$right, $left];
        }

        $isNamedVariable = $left instanceof Expr\Variable && is_string($left->name);

        return $isNamedVariable ? [$left, $right] : null;
    }

    /**
     * Preserve case, scalar type and enum/constant identity in dispatch values.
     */
    public function literal(?Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_ || $node instanceof Node\Scalar\Int_) {
            return serialize([$node->getType(), $node->value]);
        }

        if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
            return $node->class->toString().'::'.$node->name->toString();
        }

        if ($node instanceof Expr\ConstFetch) {
            return $node->name->toString();
        }

        return null;
    }
}
