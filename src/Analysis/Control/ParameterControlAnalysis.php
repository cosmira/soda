<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Control;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Tracks parameter aliases and literal dispatch to distinct direct operations.
 */
final class ParameterControlAnalysis
{
    /**
     * Mode-selection locations grouped by incoming parameter.
     */
    private array $findings = [];

    /**
     * Return parameter names and the dispatch source lines, keeping each callable isolated.
     */
    public function analyse(Node\FunctionLike $callable): array
    {
        $this->findings = [];
        $origins = [];
        foreach ($callable->getParams() as $parameter) {
            if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $origins[$parameter->var->name] = [$parameter->var->name];
            }
        }

        $this->scan($callable->getStmts() ?? [], $origins);

        return ['modes' => $this->findings];
    }

    /**
     * Report only dispatches with at least two literal alternatives and distinct operations.
     */
    private function record(Node $node, array $origins): void
    {
        $dispatch = (new LiteralDispatch)->of($node);
        if ($dispatch === null) {
            return;
        }

        [$selector, $arms] = $dispatch;
        $values = [];
        $operations = [];
        foreach ($arms as [$value, $body]) {
            $literal = $value === null ? 'default' : (new LiteralDispatch)->literal($value);
            $operation = (new OperationSequence)->encode($body);
            if ($literal !== null && $operation !== null) {
                $values[$literal] = true;
                $operations[$operation] = true;
            }
        }

        if (count($values) < 2 || count($operations) < 2) {
            return;
        }

        foreach ($this->origins($selector, $origins) as $parameter) {
            $lines = $this->findings[$parameter] ?? [];
            $lines[] = $node->getStartLine();
            $this->findings[$parameter] = $lines;
        }
    }

    /**
     * Sequential assignments replace aliases; branches conservatively merge possible origins.
     */
    private function scan(array $nodes, array $origins): array
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            $this->record($node, $origins);
            $expression = $node instanceof Stmt\Expression ? $node->expr : $node;
            if ($expression instanceof Expr\Assign && $expression->var instanceof Expr\Variable && is_string($expression->var->name)) {
                $this->scan([$expression->expr], $origins);
                $origins[$expression->var->name] = $this->origins($expression->expr, $origins);

                continue;
            }

            $origins = $this->branches($node, $origins);
        }

        return $origins;
    }

    /**
     * Only direct aliases retain an input identity; computed discriminators are separate values.
     */
    private function origins(Expr $expression, array $origins): array
    {
        return $expression instanceof Expr\Variable && is_string($expression->name) ? ($origins[$expression->name] ?? []) : [];
    }

    /**
     * Join aliases from conditional child paths without claiming a definite branch outcome.
     */
    private function branches(Node $node, array $origins): array
    {
        foreach ($node->getSubNodeNames() as $field) {
            $child = get_object_vars($node)[$field];
            $children = $child instanceof Node ? [$child] : (is_array($child) ? $child : []);
            $branch = $this->scan($children, $origins);
            foreach ($branch as $name => $sources) {
                $origins[$name] = array_values(array_unique([...($origins[$name] ?? []), ...$sources]));
            }
        }

        return $origins;
    }
}
