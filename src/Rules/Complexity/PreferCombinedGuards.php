<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\Complexity\StatementLists;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;

/**
 * Reports consecutive guard clauses that exit with the same statement.
 */
#[Rule(
    label: 'Split identical guards:',
    advice: 'Combine consecutive guards with || while preserving condition evaluation order; exits are compared by printed expression.',
)]
final class PreferCombinedGuards extends Check
{
    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_combined_guards';
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

            foreach ([$statements, ...$lists->within($scope)] as $list) {
                yield from $this->combinablePairs($list, $facts);
            }
        }
    }

    /**
     * Report each pair of adjacent guards with an identical single exit.
     *
     * @param list<Stmt> $statements
     *
     * @return iterable<Violation>
     */
    private function combinablePairs(array $statements, FileFacts $facts): iterable
    {
        $exitKey = new GuardExitKey(new Standard);
        $last = count($statements) - 1;
        for ($index = 0; $index < $last; $index++) {
            $left = $exitKey->of($statements[$index]);
            $right = $exitKey->of($statements[$index + 1]);
            if ($left === null || $left !== $right) {
                continue;
            }

            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $statements[$index + 1]->getStartLine(),
                message: sprintf(
                    'Combine the consecutive guards at lines %d and %d with ||; condition evaluation order is preserved.',
                    $statements[$index]->getStartLine(),
                    $statements[$index + 1]->getStartLine(),
                ),
            );
        }
    }
}
