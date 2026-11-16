<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Collects control-structure statement lists without crossing closure boundaries.
 */
final class StatementLists
{
    /**
     * List every non-empty control-structure body nested inside the given subtree.
     *
     * @return list<list<Stmt>>
     */
    public function within(Node $root): array
    {
        $bodies = [];
        foreach ($this->children($root) as $node) {
            if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            $statements = self::statements($node);
            if ($statements !== []) {
                $bodies[] = $statements;
            }

            array_push($bodies, ...$this->within($node));
        }

        return $bodies;
    }

    /**
     * Return the statement list held by a known control body.
     *
     * @return list<Stmt>
     */
    private static function statements(Node $node): array
    {
        $hasBody = in_array($node->getType(), [
            'Stmt_If', 'Stmt_ElseIf', 'Stmt_Else', 'Stmt_For', 'Stmt_Foreach',
            'Stmt_While', 'Stmt_Do', 'Stmt_TryCatch', 'Stmt_Catch', 'Stmt_Case',
        ], true);

        return $hasBody ? get_object_vars($node)['stmts'] : [];
    }

    /**
     * Yield direct AST children without traversing their execution scopes.
     *
     * @return iterable<Node>
     */
    private function children(Node $node): iterable
    {
        foreach ($node->getSubNodeNames() as $field) {
            $child = get_object_vars($node)[$field];
            foreach (is_array($child) ? $child : [$child] as $value) {
                if ($value instanceof Node) {
                    yield $value;
                }
            }
        }
    }

    /**
     * Report whether the last statement ends the enclosing path.
     *
     * @param list<Stmt> $statements
     */
    public static function doesEndWithExit(array $statements): bool
    {
        $last = $statements === [] ? null : $statements[count($statements) - 1];

        return $last !== null && self::isExit($last);
    }

    /**
     * Report whether the statement terminates return, throw, break, or continue paths.
     */
    public static function isExit(Node $node): bool
    {
        if ($node instanceof Stmt\Expression) {
            return $node->expr instanceof Expr\Throw_ || $node->expr instanceof Expr\Exit_;
        }

        if ($node instanceof Stmt\If_) {
            if (! $node->else instanceof Stmt\Else_ || ! self::doesEndWithExit($node->else->stmts)) {
                return false;
            }

            foreach ([$node, ...$node->elseifs] as $branch) {
                if (! self::doesEndWithExit($branch->stmts)) {
                    return false;
                }
            }

            return true;
        }

        return $node instanceof Stmt\Return_
            || $node instanceof Stmt\Break_
            || $node instanceof Stmt\Continue_;
    }
}
