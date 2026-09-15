<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;

/** Identifies declared state and control conditions belonging to one class method. */
final class CompoundConditionScope
{
    /**
     * Exclude unknown composition and property interception.
     *
     * @return array<string, true>|null
     */
    public function properties(Stmt\Class_ $class): ?array
    {
        if ($class->extends instanceof Name || $class->getTraitUses() !== [] || $this->hasMagicAccess($class)) {
            return null;
        }

        $properties = [];
        foreach ($class->getProperties() as $property) {
            if ($property->hooks !== []) {
                return null;
            }

            if (! $property->isStatic()) {
                foreach ($property->props as $item) {
                    $properties[$item->name->toString()] = true;
                }
            }
        }

        $promoted = $this->promotedProperties($class);

        return $promoted === null ? null : $properties + $promoted;
    }

    /**
     * Respect every form of magic property access.
     */
    private function hasMagicAccess(Stmt\Class_ $class): bool
    {
        foreach (['__get', '__set', '__isset', '__unset'] as $name) {
            $method = $class->getMethod($name);
            if ($method instanceof ClassMethod) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect promoted properties, rejecting hooked parameters.
     *
     * @return array<string, true>|null
     */
    private function promotedProperties(Stmt\Class_ $class): ?array
    {
        $properties = [];
        foreach ($class->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->hooks !== []) {
                return null;
            }

            $isNamed = $parameter->var instanceof Expr\Variable && is_string($parameter->var->name);
            if ($parameter->isPromoted() && $isNamed) {
                $properties[$parameter->var->name] = true;
            }
        }

        return $properties;
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
     * Traverse the current method, excluding nested callable and class scopes.
     */
    public function conditions(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            yield from $this->controlConditions($node);
            foreach ($node->getSubNodeNames() as $name) {
                $child = get_object_vars($node)[$name];
                yield from $this->conditions(is_array($child) ? $child : [$child]);
            }
        }
    }
}
