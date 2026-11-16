<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\Complexity\StatementLists;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Else_;
use PhpParser\NodeFinder;

/**
 * Reports trailing loop conditions that add a level around nested control flow.
 */
#[Rule(
    label: 'Loops ending in a wrapped remainder:',
    advice: 'Flatten nested control flow behind a trailing condition; preserve preceding work and accept flat positive branches.',
)]
final class PreferContinue extends Check
{
    /**
     * Declare an AST-only check.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Return the stable rule identifier.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_continue';
    }

    /**
     * Report trailing if statements whose negated guard can end the iteration early.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $facts): iterable
    {
        $loops = (new NodeFinder)->find($facts->nodes, static fn (Node $node): bool => in_array($node->getType(), [
            'Stmt_For', 'Stmt_Foreach', 'Stmt_While', 'Stmt_Do',
        ], true));
        foreach ($loops as $loop) {
            $statements = get_object_vars($loop)['stmts'];
            $last = $statements === [] ? null : $statements[count($statements) - 1];
            if (! $last instanceof Stmt\If_) {
                continue;
            }

            if ($last->else instanceof Else_ || $last->elseifs !== []) {
                continue;
            }

            if (StatementLists::doesEndWithExit($last->stmts) || ! $this->hasNestedControlFlow($last)) {
                continue;
            }

            yield new Violation(
                rule: $this->id(),
                file: $facts->path,
                value: 1,
                threshold: 0,
                line: $last->getStartLine(),
                message: sprintf(
                    'Negate the condition at line %d and continue; keep preceding statements in place and the remaining iteration path unindented.',
                    $last->getStartLine(),
                ),
            );
        }
    }

    /**
     * Flat work needs no guard; ambiguous jumps and finally blocks stay untouched.
     */
    private function hasNestedControlFlow(Stmt\If_ $branch): bool
    {
        $nested = false;
        foreach ($this->flowNodes($branch->stmts) as $node) {
            if ($node instanceof Stmt\Goto_ || ($node instanceof Stmt\TryCatch && $node->finally instanceof Stmt\Finally_)) {
                return false;
            }

            $nested = $nested || in_array($node->getType(), [
                'Stmt_If', 'Stmt_For', 'Stmt_Foreach', 'Stmt_While', 'Stmt_Do', 'Stmt_Switch', 'Stmt_TryCatch',
            ], true);
        }

        return $nested;
    }

    /**
     * Inspect executable children without borrowing complexity from another scope.
     *
     * @param array<Node> $nodes
     *
     * @return iterable<Node>
     */
    private function flowNodes(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            yield $node;
            foreach ($node->getSubNodeNames() as $name) {
                $value = get_object_vars($node)[$name];
                $children = array_filter(is_array($value) ? $value : [$value], static fn ($child): bool => $child instanceof Node);
                yield from $this->flowNodes($children);
            }
        }
    }
}
