<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Usage\AssignmentSyntax;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Rejects direct chains of ordinary, reference or compound assignment expressions.
 */
#[Rule(
    label: 'Chained assignments:',
    advice: 'Use separate assignments without duplicating evaluation or changing reference semantics.',
)]
final class NoChainedAssignments extends Check
{
    /**
     * Identify this assignment-chain policy in configuration and reports.
     */
    public function id(): string
    {
        return 'no_chained_assignments';
    }

    /**
     * Report the outermost assignment of each direct chain once.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $assignments = (new NodeFinder)->find($file->nodes, AssignmentSyntax::isAssignment(...));
        foreach ($assignments as $assignment) {
            if (! $this->isChainRoot($assignment)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $assignment->getStartLine(),
                message: 'Chained assignments are forbidden. Write separate assignments, preserving right-to-left evaluation, single evaluation of the value and reference semantics.',
            );
        }
    }

    /**
     * Recognize direct right-hand nesting without reporting intermediate assignments again.
     */
    private function isChainRoot(Node $assignment): bool
    {
        $isAssignment = $assignment instanceof Expr\Assign || $assignment instanceof Expr\AssignRef || $assignment instanceof Expr\AssignOp;
        if (! $isAssignment || ! AssignmentSyntax::isAssignment($assignment->expr)) {
            return false;
        }

        $parent = $assignment->getAttribute('parent');
        $isParentAssignment = $parent instanceof Expr\Assign || $parent instanceof Expr\AssignRef || $parent instanceof Expr\AssignOp;
        $isNested = $isParentAssignment && $parent->expr === $assignment;

        return ! $isNested;
    }
}
