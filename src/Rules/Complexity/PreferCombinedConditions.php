<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\Complexity\ConditionValueGrammar;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Combinable nested conditions:',
    advice: 'Use (outer) && (inner) for two simple conditions guarding one block; preserve short-circuiting, comments and domain distinctions.',
)]
final class PreferCombinedConditions extends Check
{
    /**
     * Two simple conditions can share one block without evaluating the inner condition early.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\If_::class) as $outer) {
            if (! $this->isUnbranched($outer) || count($outer->stmts) !== 1) {
                continue;
            }

            $inner = $outer->stmts[array_key_first($outer->stmts)];
            if (! $inner instanceof Stmt\If_ || ! $this->isUnbranched($inner)) {
                continue;
            }

            if ($inner->stmts === [] || $inner->getComments() !== []) {
                continue;
            }

            if (! $this->isSingleQuestion($outer->cond) || ! $this->isSingleQuestion($inner->cond)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $inner->getStartLine(),
                message: 'Combine these nested conditions as (outer) && (inner) when this clarifies one decision. Preserve evaluation order and short-circuiting; do not use the low-precedence and operator.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'prefer_combined_conditions';
    }

    /**
     * Parsed conditions and attached comments require no additional fact collectors.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Alternatives have their own path and cannot be absorbed into a conjunction.
     */
    private function isUnbranched(Stmt\If_ $branch): bool
    {
        return ! $branch->else instanceof Stmt\Else_ && $branch->elseifs === [];
    }

    /**
     * Keep compound logic and nested calculations separate instead of building a larger condition.
     */
    private function isSingleQuestion(Expr $condition): bool
    {
        $subject = ConditionValueGrammar::unwrap($condition);
        $isComparison = in_array($subject->getType(), [
            'Expr_BinaryOp_Equal', 'Expr_BinaryOp_NotEqual', 'Expr_BinaryOp_Identical', 'Expr_BinaryOp_NotIdentical',
            'Expr_BinaryOp_Greater', 'Expr_BinaryOp_GreaterOrEqual', 'Expr_BinaryOp_Smaller', 'Expr_BinaryOp_SmallerOrEqual',
        ], true);
        if ($isComparison && $subject instanceof Expr\BinaryOp) {
            return ConditionValueGrammar::isPredicateValue($subject->left) && ConditionValueGrammar::isPredicateValue($subject->right);
        }

        return ConditionValueGrammar::isPredicateValue($subject);
    }
}
