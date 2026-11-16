<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Redundant boolean return branches:',
    advice: 'Return the condition as bool or its negation once; preserve branch effects and lexical boundaries.',
)]
final class NoRedundantBooleanReturns extends Check
{
    /**
     * Collapse opposite literal returns only when neither branch performs additional work.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\If_::class) as $branch) {
            if ($branch->elseifs !== []) {
                continue;
            }

            $positive = $this->tryBooleanReturn($this->singleReturn($branch->stmts));
            $negative = $this->tryBooleanReturn($this->fallback($branch, $file));
            if ($positive === null || $negative === null || $positive === $negative) {
                continue;
            }

            $replacement = $positive ? 'return (bool) (condition)' : 'return ! (condition)';
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $branch->getStartLine(),
                message: 'Boolean return branches restate the condition. Use '.$replacement.'; evaluate the condition once and keep boolean conversion unless its result is known to be bool.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_redundant_boolean_returns';
    }

    /**
     * Resolved AST parents provide lexical adjacency without extra metric collectors.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * PHP boolean literals are case insensitive; qualified application constants are distinct.
     */
    private function tryBooleanReturn(?Stmt\Return_ $return): ?bool
    {
        $expression = $return?->expr;
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
     * Any additional statement prevents treating the branch as just a boolean result.
     *
     * @param list<Stmt> $statements
     */
    private function singleReturn(array $statements): ?Stmt\Return_
    {
        if (count($statements) !== 1) {
            return null;
        }

        $statement = $statements[array_key_first($statements)];

        return $statement instanceof Stmt\Return_ ? $statement : null;
    }

    /**
     * An adjacent return must belong to the same statement list, never an enclosing scope.
     */
    private function fallback(Stmt\If_ $branch, FileFacts $file): ?Stmt\Return_
    {
        if ($branch->else instanceof Stmt\Else_) {
            return $this->singleReturn($branch->else->stmts);
        }

        $parent = $branch->getAttribute('parent');
        $lists = $parent instanceof Node ? get_object_vars($parent) : [$file->nodes];
        foreach ($lists as $statements) {
            if (! is_array($statements)) {
                continue;
            }

            $position = array_search($branch, $statements, true);
            if (! is_int($position)) {
                continue;
            }

            $next = $statements[$position + 1] ?? null;

            return $next instanceof Stmt\Return_ ? $next : null;
        }

        return null;
    }
}
