<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;

/**
 * Finds assignments hidden inside control-flow conditions.
 *
 * @internal
 */
final readonly class AssignmentInConditionAnalyser
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
     * @param Node[] $nodes
     *
     * @return list<array{line: int}>
     */
    public function analyse(array $nodes): array
    {
        $lines = [];

        foreach ($this->conditionExpressions($nodes) as $condition) {
            foreach ($this->assignmentsIn($condition) as $assignment) {
                $line = $assignment->getStartLine();
                if ($line > 0) {
                    $lines[$line] = ['line' => $line];
                }
            }
        }

        ksort($lines, SORT_NUMERIC);

        return array_values($lines);
    }

    /**
     * @param Node[] $nodes
     *
     * @return list<Node\Expr>
     */
    private function conditionExpressions(array $nodes): array
    {
        $conditions = [];

        foreach ($this->finder->find($nodes, $this->isConditionOwner(...)) as $node) {
            array_push($conditions, ...$this->conditionsFrom($node));
        }

        return $conditions;
    }

    /**
     * Determine whether condition owner applies to the supplied input.
     */
    private function isConditionOwner(Node $node): bool
    {
        if ($this->hasSingleCondition($node)) {
            return true;
        }

        if ($node instanceof For_) {
            return true;
        }

        return $node instanceof Ternary;
    }

    /**
     * Check the supplied input for single condition.
     */
    private function hasSingleCondition(Node $node): bool
    {
        return $node instanceof If_
            || $node instanceof ElseIf_
            || $node instanceof While_
            || $node instanceof Do_;
    }

    /**
     * @return list<Node\Expr>
     */
    private function conditionsFrom(Node $node): array
    {
        $hasCondition = $node instanceof If_ || $node instanceof ElseIf_ || $node instanceof While_ || $node instanceof Do_;
        if ($hasCondition) {
            return [$node->cond];
        }

        if ($node instanceof For_) {
            return $node->cond;
        }

        if ($node instanceof Ternary) {
            return [$node->cond];
        }

        return [];
    }

    /**
     * @return list<Assign|AssignOp>
     */
    private function assignmentsIn(Node\Expr $condition): array
    {
        $assignments = [];

        foreach ($this->finder->find($condition, static fn (Node $node): bool => $node instanceof Assign || $node instanceof AssignOp) as $node) {
            $isAssignment = $node instanceof Assign || $node instanceof AssignOp;
            if ($isAssignment) {
                $assignments[] = $node;
            }
        }

        return $assignments;
    }
}
