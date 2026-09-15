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
     * Assignments have their own policy; casts and negation do not nest a computation.
     */
    private function unwrap(Expr $node): Expr
    {
        while (true) {
            if ($node instanceof Expr\BooleanNot || $node instanceof Expr\Cast) {
                $node = $node->expr;

                continue;
            }

            if ($node instanceof Expr\Assign || $node instanceof Expr\AssignRef || $node instanceof Expr\AssignOp) {
                $node = $node->expr;

                continue;
            }

            return $node;
        }
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
        $node = $this->unwrap($node);

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
        $node = $this->unwrap($node);

        $isLogical = $this->logicalOperator($node) !== null && $node instanceof Expr\BinaryOp;
        if ($isLogical) {
            return $this->isReadable($node->left) && $this->isReadable($node->right);
        }

        $isComparison = in_array($node->getType(), [
            'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual', 'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
            'Expr_BinaryOp_Greater', 'Expr_BinaryOp_GreaterOrEqual', 'Expr_BinaryOp_Smaller', 'Expr_BinaryOp_SmallerOrEqual',
        ], true) && $node instanceof Expr\BinaryOp;
        if ($isComparison) {
            return $this->isPredicateValue($node->left) && $this->isPredicateValue($node->right);
        }

        return $this->isPredicateValue($node);
    }

    /**
     * A single call or language predicate may consume simple values without nesting work.
     */
    private function isPredicateValue(Node $node): bool
    {
        $node = $node instanceof Expr ? $this->unwrap($node) : $node;

        $isPredicate = $node instanceof Expr\CallLike || in_array($node->getType(), ['Expr_Isset', 'Expr_Empty', 'Expr_Instanceof'], true);
        if ($isPredicate) {
            return $this->hasSimpleChildren($node);
        }

        return $this->isSimple($node);
    }

    /**
     * Inspect syntax children only, never parent attributes or captured callback bodies.
     */
    private function hasSimpleChildren(Node $node): bool
    {
        $properties = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $name) {
            $value = $properties[$name];
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && ! $this->isSimple($child)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Accept data access and literal values; creating a callable does not execute its body.
     */
    private function isSimple(Node $node): bool
    {
        if ($node instanceof Node\FunctionLike) {
            return true;
        }

        $isValue = $node instanceof Node\Scalar || in_array($node->getType(), [
            'Name', 'Name_FullyQualified', 'Name_Relative', 'Identifier', 'VarLikeIdentifier', 'Arg', 'VariadicPlaceholder',
            'Scalar_String', 'Scalar_Int', 'Scalar_Float', 'Scalar_InterpolatedString', 'InterpolatedStringPart',
            'Expr_Variable', 'Expr_ConstFetch', 'Expr_ClassConstFetch', 'Expr_PropertyFetch', 'Expr_NullsafePropertyFetch',
            'Expr_Cast_Int', 'Expr_Cast_String', 'Expr_Cast_Double', 'Expr_Cast_Bool', 'Expr_Cast_Array', 'Expr_Cast_Object',
            'Expr_Assign', 'Expr_AssignRef', 'Expr_BinaryOp_Coalesce', 'Expr_StaticPropertyFetch', 'Expr_ArrayDimFetch', 'Expr_Array', 'ArrayItem', 'Expr_UnaryMinus', 'Expr_UnaryPlus',
        ], true);

        return $isValue && $this->hasSimpleChildren($node);
    }
}
