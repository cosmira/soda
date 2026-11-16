<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Redundant boolean comparisons:',
    advice: 'Use the proven boolean expression or its negation once; retain strict checks on values whose type is unknown.',
)]
final class NoRedundantBooleanComparisons extends Check
{
    /**
     * Only PHP constructs with a guaranteed boolean result can lose their literal comparison.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $comparisons = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $node instanceof Expr\BinaryOp && $this->isComparison($node));
        foreach ($comparisons as $comparison) {
            assert($comparison instanceof Expr\BinaryOp);
            $literal = $this->tryBooleanLiteral($comparison->right);
            $subject = $comparison->left;
            if ($literal === null) {
                $literal = $this->tryBooleanLiteral($comparison->left);
                $subject = $comparison->right;
            }

            if ($literal === null || ! $this->isBooleanExpression($subject)) {
                continue;
            }

            $isEquality = $comparison instanceof Expr\BinaryOp\Equal || $comparison instanceof Expr\BinaryOp\Identical;
            $replacement = $literal === $isEquality ? '(boolean expression)' : '! (boolean expression)';
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $comparison->getStartLine(),
                message: 'Use '.$replacement.' instead of comparing an already boolean result with a literal. Evaluate the expression once and preserve its effects and any explicit cast.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_redundant_boolean_comparisons';
    }

    /**
     * Syntax proves these result types without requesting runtime or inferred type facts.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Ordering comparisons and the integer-valued spaceship operator are outside the policy.
     */
    private function isComparison(Expr\BinaryOp $expression): bool
    {
        return in_array($expression->getType(), [
            'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual', 'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
        ], true);
    }

    /**
     * Accept case-insensitive boolean literals without guessing application constants.
     */
    private function tryBooleanLiteral(Expr $expression): ?bool
    {
        if (! $expression instanceof Expr\ConstFetch) {
            return null;
        }

        return match (strtolower($expression->name->toString())) {
            'true'  => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * PHP logical operators return bool; bitwise operators, calls and variables need other evidence.
     */
    private function isBooleanExpression(Expr $expression): bool
    {
        return in_array($expression->getType(), [
            'Expr_BooleanNot', 'Expr_Cast_Bool', 'Expr_Isset', 'Expr_Empty', 'Expr_Instanceof',
            'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual', 'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
            'Expr_BinaryOp_Greater', 'Expr_BinaryOp_GreaterOrEqual', 'Expr_BinaryOp_Smaller', 'Expr_BinaryOp_SmallerOrEqual',
            'Expr_BinaryOp_BooleanAnd', 'Expr_BinaryOp_BooleanOr', 'Expr_BinaryOp_LogicalAnd', 'Expr_BinaryOp_LogicalOr', 'Expr_BinaryOp_LogicalXor',
        ], true);
    }
}
