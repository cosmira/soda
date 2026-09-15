<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\ConditionExtractor;
use Cosmira\Soda\Analysis\FactVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Collects mixed logical decisions and computations nested inside control predicates.
 *
 * @phpstan-type ComplexCondition array{line: int, reason: 'mixed_logic'|'nested_computation'}
 */
final class ComplexControlConditionVisitor extends FactVisitor
{
    /**
     * @var list<ComplexCondition>
     */
    private array $occurrences = [];

    /**
     * Collect each condition independently; the outer traversal also visits callable bodies.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $conditions = $node instanceof Node\Stmt\For_ ? $node->cond : [ConditionExtractor::extract($node)];
        foreach ($conditions as $condition) {
            if (! $condition instanceof Expr) {
                continue;
            }

            $reason = $this->reason($condition);
            if ($reason !== null) {
                $this->occurrences[] = ['line' => $condition->getStartLine(), 'reason' => $reason];
            }
        }
    }

    /**
     * @return list<ComplexCondition>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    /**
     * Explain structure rather than counting every AST operation as a separate decision.
     *
     * @return 'mixed_logic'|'nested_computation'|null
     */
    private function reason(Expr $condition): ?string
    {
        $operators = array_unique($this->logicalOperators($condition));
        $isMixed = count($operators) > 1 || in_array('xor', $operators, true);
        if ($isMixed) {
            return 'mixed_logic';
        }

        return $this->isReadable($condition) ? null : 'nested_computation';
    }

    /**
     * Word and symbolic operators have the same family after PHP has parsed precedence.
     */
    private function logicalOperator(Expr $node): ?string
    {
        return match ($node->getType()) {
            'Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_LogicalAnd' => 'and',
            'Expr_BinaryOp_BooleanOr', 'Expr_BinaryOp_LogicalOr'   => 'or',
            'Expr_BinaryOp_LogicalXor'                             => 'xor',
            default                                                => null,
        };
    }

    /**
     * Boolean structure ends at a predicate; callback bodies and arguments are separate work.
     *
     * @return list<string>
     */
    private function logicalOperators(Expr $node): array
    {
        $node = ConditionValueGrammar::unwrap($node);

        $operator = $this->logicalOperator($node);
        if ($operator === null || ! $node instanceof Expr\BinaryOp) {
            return [];
        }

        return [$operator, ...$this->logicalOperators($node->left), ...$this->logicalOperators($node->right)];
    }

    /**
     * Negation and homogeneous composition do not require intermediary predicates.
     */
    private function isReadable(Expr $node): bool
    {
        $node = ConditionValueGrammar::unwrap($node);

        $isLogical = $this->logicalOperator($node) !== null && $node instanceof Expr\BinaryOp;
        if ($isLogical) {
            return $this->isReadable($node->left) && $this->isReadable($node->right);
        }

        $isComparison = in_array($node->getType(), [
            'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual', 'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
            'Expr_BinaryOp_Greater', 'Expr_BinaryOp_GreaterOrEqual', 'Expr_BinaryOp_Smaller', 'Expr_BinaryOp_SmallerOrEqual',
        ], true) && $node instanceof Expr\BinaryOp;
        if ($isComparison) {
            return ConditionValueGrammar::isPredicateValue($node->left) && ConditionValueGrammar::isPredicateValue($node->right);
        }

        return ConditionValueGrammar::isPredicateValue($node);
    }
}
