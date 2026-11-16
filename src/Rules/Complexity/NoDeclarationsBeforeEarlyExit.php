<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\Complexity\StatementLists;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Usage\VariableMutation;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Else_;
use PhpParser\NodeFinder;

/**
 * Reports declarations that only feed code placed behind an early-exit guard.
 */
#[Rule(
    label: 'Declarations before early exits:',
    advice: 'Declare the variable below the guard clause so the early-exit path skips the work; keep evaluation order when moving it.',
)]
final class NoDeclarationsBeforeEarlyExit extends Check
{
    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_declarations_before_early_exit';
    }

    /**
     * Inspect callable bodies and their nested control structures.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $finder = new NodeFinder;
        $lists = new StatementLists;
        foreach ($finder->findInstanceOf($facts->nodes, FunctionLike::class) as $scope) {
            $statements = $scope->getStmts();
            if ($statements === null || $statements === []) {
                continue;
            }

            if ($finder->findFirst([$scope], static fn ($node): bool => $node->getType() === 'Stmt_Goto') instanceof Node) {
                continue;
            }

            foreach ([$statements, ...$lists->within($scope)] as $list) {
                yield from $this->declarationsBehindGuards($list, $facts, $finder);
            }
        }
    }

    /**
     * Report declarations whose only readers live after a guard clause.
     *
     * @param list<Stmt> $statements
     *
     * @return iterable<Violation>
     */
    private function declarationsBehindGuards(array $statements, FileFacts $facts, NodeFinder $finder): iterable
    {
        $last = count($statements) - 1;
        foreach ($this->declarations($statements) as $index => $variable) {
            foreach ($this->guards($statements) as $guard => $branch) {
                if ($guard <= $index) {
                    continue;
                }

                $firstRead = $index + 1;
                $afterGuard = $guard + 1;
                $usedBeforeGuard = $this->isUsed($statements, $firstRead, $guard, $variable, $finder);
                $between = [...array_slice($statements, $firstRead, $guard - $firstRead), $branch->cond];
                if ($usedBeforeGuard
                    || $this->doesInputChange($statements[$index], $between, $finder)) {
                    continue;
                }

                $usedAfterGuard = $this->isUsed($statements, $afterGuard, $last, $variable, $finder);
                if (! $usedAfterGuard) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(),
                    file: $facts->path,
                    value: 1,
                    threshold: 0,
                    line: $statements[$index]->getStartLine(),
                    message: sprintf(
                        'Declare $%s after the guard at line %d; the early-exit path never reads it.',
                        $variable,
                        $statements[$guard]->getStartLine(),
                    ),
                );
                break;
            }
        }
    }

    /**
     * A snapshot must stay before writes to the variables used to calculate it.
     */
    private function doesInputChange(Node $declaration, array $between, NodeFinder $finder): bool
    {
        foreach ($finder->findInstanceOf([$declaration], Variable::class) as $input) {
            $name = $input->name;
            if (is_string($name) && $finder->findFirst($between, static fn (Node $node): bool => VariableMutation::isMutationOf($node, $name)) instanceof Node) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map statement indexes to the plain variables they declare.
     *
     * @param list<Stmt> $statements
     *
     * @return array<int, string>
     */
    private function declarations(array $statements): array
    {
        $declared = [];
        foreach ($statements as $index => $statement) {
            $variable = $this->declaredVariable($statement);
            if ($variable !== null) {
                $declared[$index] = $variable;
            }
        }

        return $declared;
    }

    /**
     * Accept statement-level assignments to plain local variables.
     */
    private function declaredVariable(Node $statement): ?string
    {
        if (! $statement instanceof Stmt\Expression) {
            return null;
        }

        $expression = $statement->expr;
        if (! $expression instanceof Expr\Assign || $this->hasEagerEffect($expression->expr)) {
            return null;
        }

        $target = $expression->var;
        if (! $target instanceof Variable || ! is_string($target->name)) {
            return null;
        }

        return $target->name;
    }

    /**
     * Invoked code can affect the exiting path; nested callable bodies are deferred.
     */
    private function hasEagerEffect(Node $node): bool
    {
        if ($node instanceof FunctionLike || $node->getType() === 'Stmt_Class') {
            return false;
        }

        if (str_starts_with($node->getType(), 'Expr_AssignOp_') || in_array($node->getType(), [
            'Expr_Assign', 'Expr_AssignRef', 'Expr_PreInc', 'Expr_PostInc', 'Expr_PreDec', 'Expr_PostDec',
            'Expr_Yield', 'Expr_YieldFrom', 'Expr_Print', 'Expr_Include', 'Expr_Eval', 'Expr_Exit', 'Expr_Throw',
            'Expr_FuncCall', 'Expr_MethodCall', 'Expr_NullsafeMethodCall', 'Expr_StaticCall',
            'Expr_New', 'Expr_Clone', 'Expr_ShellExec',
            'Expr_PropertyFetch', 'Expr_NullsafePropertyFetch', 'Expr_StaticPropertyFetch',
        ], true)) {
            return true;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $value = get_object_vars($node)[$name];
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->hasEagerEffect($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Collect guard clauses whose every branch exits the enclosing path.
     *
     * @param list<Stmt> $statements
     *
     * @return array<int, Stmt\If_>
     */
    private function guards(array $statements): array
    {
        $guards = [];
        foreach ($statements as $index => $statement) {
            if ($statement instanceof Stmt\If_ && $this->isGuard($statement)) {
                $guards[$index] = $statement;
            }
        }

        return $guards;
    }

    /**
     * Require the guarded branch to exit while the remaining branches, if any, do the same.
     */
    private function isGuard(Stmt\If_ $if): bool
    {
        if (! StatementLists::doesEndWithExit($if->stmts)) {
            return false;
        }

        foreach ($if->elseifs as $elseif) {
            if (! StatementLists::doesEndWithExit($elseif->stmts)) {
                return false;
            }
        }

        return ! $if->else instanceof Else_ || StatementLists::doesEndWithExit($if->else->stmts);
    }

    /**
     * Search statement subtrees for reads of the named variable.
     *
     * @param list<Stmt> $statements
     */
    private function isUsed(array $statements, int $from, int $to, string $variable, NodeFinder $finder): bool
    {
        for ($index = $from; $index <= $to; $index++) {
            $uses = $finder->find([$statements[$index]], static fn ($node): bool => $node instanceof Variable
                && is_string($node->name)
                && $node->name === $variable);
            if ($uses !== []) {
                return true;
            }
        }

        return false;
    }
}
