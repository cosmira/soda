<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\CallableIdentity;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Soda PHP cognitive v1: independent callable scopes with explainable increments.
 *
 * @phpstan-type CognitiveRow array{name: string, line: int, cognitive_complexity: int, cognitive_contributions: list<array{line: int, increment: int, reason: string}>}
 */
final class CognitiveComplexity
{
    use CognitiveConditions;
    use CognitiveRecursion;

    /**
     * Contributions belong to the current callable only and retain no AST nodes.
     *
     * @var list<array{line: int, increment: int, reason: string}>
     */
    private array $contributions = [];

    /**
     * The current resolved callable name is used for syntactic direct recursion.
     */
    private string $name = '';

    /**
     * A callable incurs one direct-recursion point regardless of call-site count.
     */
    private bool $recursive = false;

    /**
     * Calculate each callable independently, including closures and anonymous methods.
     *
     * @param list<Node> $nodes
     *
     * @return array<string, CognitiveRow>
     */
    public function collect(array $nodes): array
    {
        $result = [];
        $callables = (new NodeFinder)->find($nodes, fn (Node $node): bool => $node instanceof FunctionLike);
        foreach ($callables as $callable) {
            $isCallable = $callable instanceof FunctionLike;
            if (! $isCallable) {
                continue;
            }

            $this->name = CallableIdentity::name($callable);
            $this->contributions = [];
            $this->recursive = false;
            foreach ($callable->getStmts() ?? [] as $statement) {
                $this->scan($statement, 0);
            }

            $result[$this->name] = [
                'name'                    => $this->name, 'line' => $callable->getStartLine(),
                'cognitive_complexity'    => array_sum(array_column($this->contributions, 'increment')),
                'cognitive_contributions' => $this->contributions,
            ];
        }

        return $result;
    }

    /**
     * Discount coalescing and shorthand ternaries; switch/match count once, not per arm.
     */
    private function structuralIncrement(Node $node, int $depth): int
    {
        $isShortTernary = $node instanceof Node\Expr\Ternary && ! $node->if instanceof Node\Expr;
        if ($isShortTernary) {
            return 0;
        }

        return match ($node->getType()) {
            'Stmt_For', 'Stmt_Foreach', 'Stmt_While', 'Stmt_Do', 'Stmt_Catch',
            'Stmt_Switch', 'Expr_Match', 'Expr_Ternary' => 1 + $depth,
            default                                     => 0,
        };
    }

    /**
     * Explain the exact source of each point without retaining the syntax tree.
     */
    private function record(Node $node, int $increment, string $reason): void
    {
        $this->contributions[] = ['line' => $node->getStartLine(), 'increment' => $increment, 'reason' => $reason];
    }

    /**
     * Only goto and explicitly multilevel loop jumps add a point; guards do not.
     */
    private function recordJump(Node $node): void
    {
        $isLoopJump = $node instanceof Stmt\Break_ || $node instanceof Stmt\Continue_;
        $isMultilevel = $isLoopJump && $node->num instanceof Node\Scalar\Int_ && $node->num->value > 1;
        $isJump = $node instanceof Stmt\Goto_ || $isMultilevel;
        if ($isJump) {
            $this->record($node, 1, 'multilevel jump');
        }
    }

    /**
     * Walk executable syntax while preserving independent nested callable boundaries.
     */
    private function scan(Node $node, int $depth): void
    {
        $isBoundary = $node instanceof FunctionLike || $node instanceof Stmt\ClassLike;
        if ($isBoundary) {
            return;
        }

        if ($node instanceof Stmt\If_) {
            $this->scanIf($node, $depth);

            return;
        }

        $isBoolean = $this->operator($node) !== null;
        if ($isBoolean) {
            $this->scanBoolean($node, $depth);

            return;
        }

        $increment = $this->structuralIncrement($node, $depth);
        if ($increment > 0) {
            $this->record($node, $increment, $node->getType());
        }

        $this->recordJump($node);
        $this->recordRecursion($node);
        $bodyDepth = $increment > 0 ? $depth + 1 : $depth;
        $this->scanChildren($node, $depth, $bodyDepth);
    }

    /**
     * Score headers at the current depth and executable branch bodies at their nested depth.
     */
    private function scanChildren(Node $node, int $depth, int $bodyDepth): void
    {
        $properties = get_object_vars($node);
        foreach ($node->getSubNodeNames() as $field) {
            $value = $properties[$field];
            $childDepth = in_array($field, ['stmts', 'cases', 'arms', 'if', 'else'], true) ? $bodyDepth : $depth;
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $this->scan($child, $childDepth);
                }
            }
        }
    }
}
