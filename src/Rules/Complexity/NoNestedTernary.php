<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Ternary;
use PhpParser\NodeFinder;

/**
 * Reports a full ternary used directly as a branch of another full ternary.
 */
#[Rule(
    label: 'Nested full ternaries:',
    advice: 'Name the inner decision or use explicit control flow; keep shorthand fallbacks and evaluation order intact.',
)]
final class NoNestedTernary extends Check
{
    /**
     * Return the stable identifier used by reports and configuration.
     */
    public function id(): string
    {
        return 'no_nested_ternary';
    }

    /**
     * Inspect parsed expressions without requesting metric collectors.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report each outer decision once without crossing callable or argument boundaries.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Ternary::class) as $node) {
            $hasNestedBranch = $node->if instanceof Expr && $this->hasNestedBranch($node);
            if (! $hasNestedBranch) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $node->getStartLine(),
                message: 'A full ternary contains another full ternary in its result branch. Name the inner decision or use explicit control flow, preserving evaluation order.',
            );
        }
    }

    /**
     * Check the two result branches without including the condition or nested call bodies.
     */
    private function hasNestedBranch(Ternary $node): bool
    {
        return $node->if instanceof Expr && ($this->isFullTernary($node->if) || $this->isFullTernary($node->else));
    }

    /**
     * Recognize full decisions while leaving shorthand fallback expressions accepted.
     */
    private function isFullTernary(Expr $expression): bool
    {
        return $expression instanceof Ternary && $expression->if instanceof Expr;
    }
}
