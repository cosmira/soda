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
use PhpParser\PrettyPrinter\Standard;

/**
 * Reports identical expression branches without claiming their conditions are disposable.
 */
#[Rule(
    label: 'Identical expression branches:',
    advice: 'Consolidate identical results when useful; preserve condition effects, match selection and unmatched-value behavior.',
)]
final class NoIdenticalBranches extends Check
{
    /**
     * Return the stable identifier used by reports and configuration.
     */
    public function id(): string
    {
        return 'no_identical_branches';
    }

    /**
     * Compare full ternary branches and explicit match arms, preserving fallback boundaries.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $finder = new NodeFinder;
        $nodes = $finder->find($file->nodes, fn (Node $node): bool => $node instanceof Expr\Ternary || $node instanceof Expr\Match_);
        foreach ($nodes as $node) {
            foreach ($this->duplicates($node) as $lines) {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: count($lines) - 1, threshold: 0,
                    line: $lines[1],
                    message: 'Identical branch expressions at lines '.implode(', ', $lines).'. Consider consolidating the result; preserve condition evaluation, match selection and unmatched-value behavior.',
                );
            }
        }
    }

    /**
     * Group repeated expressions by printed syntax, ignoring formatting and comments.
     *
     * @return list<list<int>>
     */
    private function duplicates(Node $node): array
    {
        $groups = [];
        $printer = new Standard;
        foreach ($this->branches($node) as $branch) {
            if (! $this->isComparable($branch)) {
                continue;
            }

            $key = $printer->prettyPrintExpr($branch);
            $lines = $groups[$key] ?? [];
            $lines[] = $branch->getStartLine();
            $groups[$key] = $lines;
        }

        return array_values(array_filter($groups, fn (array $lines): bool => count($lines) > 1));
    }

    /**
     * Keep default match arms and shorthand ternaries outside the comparison.
     *
     * @return list<Expr>
     */
    private function branches(Node $node): array
    {
        if ($node instanceof Expr\Ternary && $node->if instanceof Expr) {
            return [$node->if, $node->else];
        }

        if ($node instanceof Expr\Match_) {
            $arms = array_filter($node->arms, fn (Node\MatchArm $arm): bool => $arm->conds !== null);

            return array_values(array_map(fn (Node\MatchArm $arm): Expr => $arm->body, $arms));
        }

        return [];
    }

    /**
     * Exclude expressions whose location or declaration identity can change their meaning.
     */
    private function isComparable(Expr $branch): bool
    {
        $sensitive = (new NodeFinder)->findFirst([$branch], fn (Node $node): bool => $node instanceof Node\Scalar\MagicConst
            || $node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike);

        return ! $sensitive instanceof Node;
    }
}
