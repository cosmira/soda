<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Scores conditional branches and boolean runs within the owning cognitive calculation.
 */
trait CognitiveConditions
{
    /**
     * Elseif and else add a flat point; their bodies share the original if's nesting.
     */
    private function scanIf(Stmt\If_ $node, int $depth): void
    {
        $this->record($node, 1 + $depth, 'if');
        $this->scan($node->cond, $depth);
        foreach ($node->stmts as $statement) {
            $this->scan($statement, $depth + 1);
        }

        foreach ([...$node->elseifs, ...($node->else instanceof Stmt\Else_ ? [$node->else] : [])] as $branch) {
            $this->record($branch, 1, 'else/elseif');
            if ($branch instanceof Stmt\ElseIf_) {
                $this->scan($branch->cond, $depth);
            }

            foreach ($branch->stmts as $statement) {
                $this->scan($statement, $depth + 1);
            }
        }
    }

    /**
     * Count operator changes in source order, independently of left/right tree grouping.
     */
    private function scanBoolean(Node $node, int $depth): void
    {
        $parts = $this->booleanParts($node);
        $previous = null;
        foreach ($parts['operators'] as $operator) {
            $kind = $this->operator($operator);
            if ($kind !== $previous) {
                $this->record($operator, 1, 'boolean '.$kind.' sequence');
                $previous = $kind;
            }
        }

        foreach ($parts['leaves'] as $leaf) {
            $this->scan($leaf, $depth);
        }
    }

    /**
     * Flatten only boolean binaries; negation and other expressions form new runs.
     *
     * @return array{operators: list<Node>, leaves: list<Node>}
     */
    private function booleanParts(Node $node): array
    {
        $isBinary = $node instanceof Node\Expr\BinaryOp && $this->operator($node) !== null;
        if (! $isBinary) {
            return ['operators' => [], 'leaves' => [$node]];
        }

        $left = $this->booleanParts($node->left);
        $right = $this->booleanParts($node->right);

        return [
            'operators' => [...$left['operators'], $node, ...$right['operators']],
            'leaves'    => [...$left['leaves'], ...$right['leaves']],
        ];
    }

    /**
     * Treat word and symbolic operators as the same operation after PHP parsing.
     */
    private function operator(Node $node): ?string
    {
        return match ($node->getType()) {
            'Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_LogicalAnd' => 'and',
            'Expr_BinaryOp_BooleanOr', 'Expr_BinaryOp_LogicalOr'   => 'or',
            'Expr_BinaryOp_LogicalXor'                             => 'xor',
            default                                                => null,
        };
    }
}
