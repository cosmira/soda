<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Usage\AssignmentSyntax;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Reports hidden assignments in returns while preserving direct owner updates and lazy initialization.
 */
#[Rule(
    label: 'Assignments inside returns:',
    advice: 'Keep hidden local and nested writes out of returns; direct named owner updates and lazy initialization are accepted. Preserve evaluation order and reference semantics.',
)]
final class NoAssignmentInReturn extends Check
{
    /**
     * Configure explicit exceptions for visible owner updates and lazy-cache returns.
     */
    public function __construct(
        /**
         * Accept a direct lazy initialization when returning its cached value.
         */
        private readonly bool $allowLazyInitialization = true,
        /**
         * Accept one directly visible update of the returning owner's named state.
         */
        private readonly bool $allowOwnerStateReturn = true,
        /**
         * Keep concise arrow callbacks whose only assignments update named owner state.
         */
        private readonly bool $allowArrowStateUpdates = true,
    ) {}

    /**
     * Identify this return-expression policy in configuration and reports.
     */
    public function id(): string
    {
        return 'no_assignment_in_return';
    }

    /**
     * Report each return expression once while respecting nested callable boundaries.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $finder = new NodeFinder;
        $owners = [...$finder->findInstanceOf($file->nodes, Return_::class), ...$finder->findInstanceOf($file->nodes, ArrowFunction::class)];
        foreach ($owners as $owner) {
            if ($owner->expr === null || ! AssignmentSyntax::hasAssignment($owner->expr) || $this->isAllowedReturn($owner)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $owner->getStartLine(),
                message: 'This assignment is outside the configured return-expression policy. Make the state change explicit before returning, preserving evaluation order and reference semantics.',
            );
        }
    }

    /**
     * Allow explicit single-state idioms without admitting hidden local or chained writes.
     */
    private function isAllowedReturn(Return_|ArrowFunction $owner): bool
    {
        if ($this->isLazyInitialization($owner->expr)) {
            return true;
        }

        if ($owner instanceof ArrowFunction) {
            return $this->allowArrowStateUpdates && ReturnAssignmentSyntax::isOwnerStateExpression($owner->expr);
        }

        return $this->allowOwnerStateReturn && ReturnAssignmentSyntax::isOwnerWrite($owner->expr);
    }

    /**
     * Accept one top-level ??= with a stable target and no further assignment in its value.
     */
    private function isLazyInitialization(Expr $expression): bool
    {
        if (! $this->allowLazyInitialization || ! $expression instanceof Expr\AssignOp\Coalesce
            || AssignmentSyntax::hasAssignment($expression->expr)) {
            return false;
        }

        return ReturnAssignmentSyntax::isStableTarget($expression->var);
    }
}
