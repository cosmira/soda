<?php

declare(strict_types=1);

namespace Cosmira\Soda\Research\ExplicitBehavior;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/** Research candidate: counts structural repetition, not shared domain meaning. */
final class NoRepeatedCompoundConditions extends Check
{
    public function __construct(private readonly int $minMethods = 3)
    {
        if ($minMethods < 2) {
            throw new InvalidArgumentException('minMethods must be at least 2.');
        }
    }

    public function id(): string
    {
        return 'no_repeated_compound_conditions';
    }

    public function requiredAnalyses(): array
    {
        return [];
    }

    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Class_::class) as $class) {
            $properties = $this->properties($class);
            if ($properties === null || $class->name === null) {
                continue;
            }
            $groups = [];
            foreach ($class->getMethods() as $method) {
                foreach ($this->conditions($method->stmts ?? []) as $condition) {
                    $key = $this->fingerprint($condition, $properties);
                    if ($key === null || ! $this->hasBooleanOperator($condition)) {
                        continue;
                    }
                    $groups[$key][$method->name->toString()][] = $condition->getStartLine();
                }
            }
            foreach ($groups as $methods) {
                if (count($methods) < $this->minMethods) {
                    continue;
                }
                $locations = [];
                $lines = [];
                foreach ($methods as $method => $positions) {
                    $locations[] = $method.'() at lines '.implode(', ', array_unique($positions));
                    array_push($lines, ...$positions);
                }
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: count($methods), threshold: $this->minMethods - 1,
                    class: isset($class->namespacedName) ? $class->namespacedName->toString() : $class->name->toString(),
                    line: min($lines),
                    message: sprintf('The same compound condition appears in %d methods: %s. Extract a named predicate if these occurrences express the same decision.', count($methods), implode('; ', $locations)),
                );
            }
        }
    }

    /** @return array<string, true>|null */
    private function properties(Stmt\Class_ $class): ?array
    {
        if ($class->extends !== null || $class->getTraitUses() !== []
            || $class->getMethod('__get') !== null || $class->getMethod('__set') !== null
            || $class->getMethod('__isset') !== null || $class->getMethod('__unset') !== null) {
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
        foreach ($class->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->hooks !== []) {
                return null;
            }
            if ($parameter->isPromoted() && $parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $properties[$parameter->var->name] = true;
            }
        }

        return $properties;
    }

    /** Walk the current method only; nested functions and classes own their conditions. */
    private function conditions(array $nodes): iterable
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }
            if ($node instanceof Stmt\If_ || $node instanceof Stmt\ElseIf_
                || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_ || $node instanceof Expr\Ternary) {
                yield $node->cond;
            }
            // Only the final for-expression controls the loop; preceding expressions are preparation.
            if ($node instanceof Stmt\For_ && $node->cond !== []) {
                yield $node->cond[array_key_last($node->cond)];
            }
            foreach ($node->getSubNodeNames() as $name) {
                $child = get_object_vars($node)[$name];
                yield from $this->conditions(is_array($child) ? $child : [$child]);
            }
        }
    }

    private function hasBooleanOperator(Expr $expression): bool
    {
        if ($expression instanceof Expr\BinaryOp\BooleanAnd || $expression instanceof Expr\BinaryOp\BooleanOr) {
            return true;
        }
        if ($expression instanceof Expr\BooleanNot) {
            return $this->hasBooleanOperator($expression->expr);
        }
        if ($expression instanceof Expr\BinaryOp) {
            return $this->hasBooleanOperator($expression->left) || $this->hasBooleanOperator($expression->right);
        }

        return false;
    }

    /** Only encode the closed, side-effect-free syntax vocabulary specified by the RFC. */
    private function fingerprint(Expr $expression, array $properties): ?string
    {
        if ($expression instanceof Expr\PropertyFetch && $expression->var instanceof Expr\Variable
            && $expression->var->name === 'this' && $expression->name instanceof Node\Identifier
            && isset($properties[$expression->name->toString()])) {
            return serialize(['property', $expression->name->toString()]);
        }
        if ($expression instanceof Node\Scalar\String_ || $expression instanceof Node\Scalar\Int_ || $expression instanceof Node\Scalar\Float_) {
            return serialize([$expression->getType(), $expression->value]);
        }
        if ($expression instanceof Expr\ConstFetch
            && in_array(strtolower($expression->name->toString()), ['true', 'false', 'null'], true)) {
            return serialize(['literal', strtolower($expression->name->toString())]);
        }
        if ($expression instanceof Expr\BooleanNot || $expression instanceof Expr\UnaryMinus || $expression instanceof Expr\UnaryPlus) {
            if (! $expression instanceof Expr\BooleanNot
                && ! $expression->expr instanceof Node\Scalar\Int_ && ! $expression->expr instanceof Node\Scalar\Float_) {
                return null;
            }
            $operand = $this->fingerprint($expression->expr, $properties);

            return $operand === null ? null : serialize([$expression->getType(), $operand]);
        }
        if ($expression instanceof Expr\BinaryOp\BooleanAnd || $expression instanceof Expr\BinaryOp\BooleanOr
            || $expression instanceof Expr\BinaryOp\Identical || $expression instanceof Expr\BinaryOp\NotIdentical) {
            $left = $this->fingerprint($expression->left, $properties);
            $right = $this->fingerprint($expression->right, $properties);

            return $left === null || $right === null ? null : serialize([$expression->getType(), $left, $right]);
        }

        return null;
    }
}
