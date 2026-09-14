<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Finds a final catch whose only executable statement rethrows its bound variable.
 * This is a review signal, not an automatically safe deletion.
 */
final class NoRedundantRethrow extends Check
{
    /**
     * Inspect the shared AST without collecting additional metrics.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Earlier catches can shield a later broad handler and are deliberately excluded.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\TryCatch::class) as $try) {
            if ($try->catches === []) {
                continue;
            }

            $catch = $try->catches[array_key_last($try->catches)] ?? null;
            $isCandidate = $catch instanceof Stmt\Catch_ && $this->isOnlyRethrow($catch);
            if ($isCandidate) {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    line: $catch->getStartLine(),
                    message: 'The final catch only rethrows its bound exception. Review whether it is needed; preserve exception-variable scope and finally behavior when simplifying.',
                );
            }
        }
    }

    /**
     * Ignore comments and empty statements, but retain every executable side effect.
     */
    private function isOnlyRethrow(Stmt\Catch_ $catch): bool
    {
        $statements = array_filter($catch->stmts, fn (Node $node): bool => ! $node instanceof Stmt\Nop);
        $statement = reset($statements);
        $isThrow = count($statements) === 1 && $statement instanceof Stmt\Expression
            && $statement->expr instanceof Expr\Throw_;
        if (! $isThrow) {
            return false;
        }

        $thrown = $statement->expr->expr;

        return $catch->var instanceof Expr\Variable && $thrown instanceof Expr\Variable && $thrown->name === $catch->var->name;
    }

    /**
     * Identify this optional check in configuration and reports.
     */
    public function id(): string
    {
        return 'no_redundant_rethrow';
    }
}
