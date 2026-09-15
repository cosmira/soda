<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeFinder;

/** Review repeated positional fields only when an explicit tuple contract establishes them. */
final class NoNumericArrayIndex extends Check
{
    /**
     * Read the existing AST without requesting other analyses.
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Preserve the published rule identifier.
     */
    public function id(): string
    {
        return 'no_numeric_array_index';
    }

    /**
     * Review fixed tuple fields within each independently documented callable.
     */
    public function checkFile(FileFacts $facts): iterable
    {
        $callables = (new NodeFinder)->find($facts->nodes, static fn (Node $node): bool => $node instanceof FunctionLike);
        foreach ($callables as $callable) {
            if (! $callable instanceof FunctionLike) {
                continue;
            }

            foreach ($this->tuples($callable) as $name => $positions) {
                $reads = [];
                $known = $this->checkReads($callable->getStmts() ?? [], $name, $positions, $reads);
                if (! $known || count($reads) < 2) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $facts->path, value: 1, threshold: 0,
                    line: min($reads),
                    message: sprintf('Review positional fields of $%s: several fixed tuple positions are read. Consider named fields if they clarify their meaning.', $name),
                );
            }
        }
    }

    /**
     * Match native array parameters to a single supported tuple annotation.
     *
     * @return array<string, list<int>>
     */
    private function tuples(FunctionLike $callable): array
    {
        $doc = $callable->getDocComment()?->getText() ?? '';
        preg_match_all('/@param\s+array\{([^{}]+)\}\s+\$(\w+)(?=\s|\*\/|$)/', $doc, $matches, PREG_SET_ORDER);
        $tuples = [];
        foreach ($matches as $match) {
            $tuples[$match[2]] = array_key_exists($match[2], $tuples) ? [] : $this->positions($match[1]);
        }

        $result = [];
        foreach ($callable->getParams() as $param) {
            $name = $this->parameterName($param);
            $shape = $tuples[$name] ?? [];
            if (count($shape) >= 2) {
                $result[$name] = $shape;
            }
        }

        return $result;
    }

    /**
     * Recognize only closed scalar tuples with contiguous, required positions.
     *
     * @return list<int>
     */
    private function positions(string $shape): array
    {
        $fields = array_map(trim(...), explode(',', $shape));
        $positions = [];
        foreach ($fields as $index => $field) {
            if (! preg_match('/^(?:(\d+)\s*:\s*)?(?:string|int|float|bool|null)$/D', $field, $parts)) {
                return [];
            }

            if (isset($parts[1]) && (int) $parts[1] !== $index) {
                return [];
            }

            $positions[] = $index;
        }

        return $positions;
    }

    /**
     * Exclude reference, variadic and uncertain parameter contracts.
     */
    private function parameterName(Node\Param $param): string
    {
        if ($param->byRef || $param->variadic || ! $param->var instanceof Expr\Variable) {
            return '';
        }

        if (! $param->type instanceof Node\Identifier || $param->type->name !== 'array') {
            return '';
        }

        return is_string($param->var->name) ? $param->var->name : '';
    }

    /**
     * Accept only straight-line scalar reads. All other syntax is unknown,
     * including writes, reference escapes, callbacks and conditional evaluation.
     *
     * @param list<Node>      $nodes
     * @param list<int>       $positions
     * @param array<int, int> $reads
     */
    private function checkReads(array $nodes, string $name, array $positions, array &$reads): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Expr\ArrayDimFetch) {
                if (! $this->checkPosition($node, $name, $positions, $reads)) {
                    return false;
                }

                continue;
            }

            $scalar = in_array($node->getType(), [
                'Stmt_Return', 'Stmt_Echo', 'Stmt_Expression', 'Expr_BinaryOp_Concat',
                'Expr_BinaryOp_Plus', 'Expr_BinaryOp_Minus', 'Expr_BinaryOp_Mul',
                'Expr_Cast_String', 'Scalar_String', 'Scalar_Int', 'Scalar_Float',
            ], true);
            if (! $scalar) {
                return false;
            }

            $children = $this->children($node);
            if (! $this->checkReads($children, $name, $positions, $reads)) {
                return false;
            }

            if ($node->getType() === 'Stmt_Return') {
                break;
            }
        }

        return true;
    }

    /**
     * Record a fixed read without following it into another lexical scope.
     *
     * @param list<int>       $positions
     * @param array<int, int> $reads
     */
    private function checkPosition(Expr\ArrayDimFetch $node, string $name, array $positions, array &$reads): bool
    {
        if (! $node->var instanceof Expr\Variable || $node->var->name !== $name) {
            return false;
        }

        if (! $node->dim instanceof Int_ || ! in_array($node->dim->value, $positions, true)) {
            return false;
        }

        $reads[$node->dim->value] ??= $node->getStartLine();

        return true;
    }

    /**
     * Traverse declared AST children, excluding attributes and comments.
     *
     * @return list<Node>
     */
    private function children(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $key) {
            $value = get_object_vars($node)[$key];
            $values = is_array($value) ? $value : [$value];
            array_push($children, ...array_filter($values, static fn ($child): bool => $child instanceof Node));
        }

        return $children;
    }
}
