<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Identifies declared state and control conditions belonging to one class method.
 */
final class CompoundConditionScope
{
    /**
     * Exclude unknown composition and property interception.
     *
     * @return array<string, true>
     */
    public function properties(Stmt\Class_ $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            if ($property->hooks !== []) {
                continue;
            }

            if (! $property->isStatic()) {
                foreach ($property->props as $item) {
                    $properties[$item->name->toString()] = true;
                }
            }
        }

        $promoted = $this->promotedProperties($class);

        return $properties + $promoted;
    }

    /**
     * Collect promoted properties, rejecting hooked parameters.
     *
     * @return array<string, true>
     */
    private function promotedProperties(Stmt\Class_ $class): array
    {
        $properties = [];
        foreach ($class->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->hooks !== []) {
                continue;
            }

            $isNamed = $parameter->var instanceof Expr\Variable && is_string($parameter->var->name);
            if ($parameter->isPromoted() && $isNamed) {
                $properties[$parameter->var->name] = true;
            }
        }

        return $properties;
    }

    /**
     * Traverse the current method, excluding nested callable and class scopes.
     */
    public function conditions(array $nodes): iterable
    {
        $aliases = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            $assignment = $node instanceof Stmt\Expression ? $node->expr : null;
            if ($assignment instanceof Expr\Assign && $assignment->var instanceof Expr\Variable && is_string($assignment->var->name)) {
                $value = ConditionSnapshot::normalize($assignment->expr, $aliases);
                yield from $this->conditions([$value]);
                // Keep only the immediately preceding snapshot or its direct alias chain.
                $aliases = [$assignment->var->name => $value];

                continue;
            }

            foreach ($this->controlConditions($node) as $condition) {
                yield ConditionSnapshot::normalize($condition, $aliases);
            }

            $aliases = [];
            yield from $this->nestedConditions($node);

        }
    }

    /**
     * Select expressions controlling branches and loops, not loop preparation.
     */
    private function controlConditions(Node $node): iterable
    {
        $isBranch = $node instanceof Stmt\If_ || $node instanceof Stmt\ElseIf_ || $node instanceof Expr\Ternary;
        $isLoop = $node instanceof Stmt\While_ || $node instanceof Stmt\Do_;
        if ($isBranch || $isLoop) {
            yield $node->cond;
        }

        if ($node instanceof Stmt\For_ && $node->cond !== []) {
            yield $node->cond[array_key_last($node->cond)];
        }
    }

    /**
     * Descend into nested expressions after the immediate snapshot scope has ended.
     */
    private function nestedConditions(Node $node): iterable
    {
        foreach ($node->getSubNodeNames() as $name) {
            $child = get_object_vars($node)[$name];
            yield from $this->conditions(is_array($child) ? $child : [$child]);
        }
    }
}
