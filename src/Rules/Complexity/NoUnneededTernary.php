<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Reports boolean ternaries whose result is expressible by conversion, negation or short-circuiting.
 */
#[Rule(
    label: 'Unneeded boolean ternaries:',
    advice: 'Preserve boolean conversion and evaluate the condition once when removing literal boolean branches; preserve short-circuit branch effects.',
)]
final class NoUnneededTernary extends Check
{
    /**
     * Identify the boolean-result simplification policy.
     */
    public function id(): string
    {
        return 'no_unneeded_ternary';
    }

    /**
     * Preserve boolean conversion, condition evaluation and the selected branch's effects.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Expr\Ternary::class) as $node) {
            $replacement = $this->replacement($node);
            if ($replacement === null) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $node->getStartLine(),
                message: 'Unneeded boolean ternary. Use '.$replacement.'; remove the cast only when the condition is known to return bool. Evaluate the condition once and preserve short-circuit evaluation of the selected branch.',
            );
        }
    }

    /**
     * A non-literal branch must already return bool before a logical operator can replace selection.
     */
    private function replacement(Expr\Ternary $node): ?string
    {
        if (! $node->if instanceof Expr) {
            return null;
        }

        $positive = $this->tryBooleanLiteral($node->if);
        $negative = $this->tryBooleanLiteral($node->else);
        if ($positive !== null && $negative !== null) {
            if ($positive === $negative) {
                return null;
            }

            return $positive ? '(bool) (condition)' : '! (condition)';
        }

        return $this->partialReplacement($node, $positive, $negative);
    }

    /**
     * One literal branch supplies the identity or absorbing result of a short-circuit operator.
     */
    private function partialReplacement(Expr\Ternary $node, ?bool $positive, ?bool $negative): ?string
    {
        if ($positive !== null && $this->isBooleanExpression($node->else)) {
            return $positive ? '(condition) || (else expression)' : '! (condition) && (else expression)';
        }

        if ($negative !== null && $node->if instanceof Expr && $this->isBooleanExpression($node->if)) {
            return $negative ? '! (condition) || (then expression)' : '(condition) && (then expression)';
        }

        return null;
    }

    /**
     * Accept PHP boolean literals only, including case-insensitive spellings.
     */
    private function tryBooleanLiteral(?Expr $node): ?bool
    {
        if (! $node instanceof Expr\ConstFetch) {
            return null;
        }

        return match (strtolower($node->name->toString())) {
            'true'  => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * PHP syntax guarantees these result types; calls, variables and spaceship results need other proof.
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
