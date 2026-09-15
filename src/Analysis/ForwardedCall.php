<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Recognizes one operation with unchanged inputs, including disposable local aliases.
 */
final class ForwardedCall
{
    /**
     * Return a normalized copy while preserving the source AST and parameter binding.
     */
    public function of(Stmt\ClassMethod $method): Expr\MethodCall|Expr\StaticCall|null
    {
        if ($method->byRef) {
            return null;
        }

        [$expression, $aliases] = $this->expression($method->stmts ?? []);
        if (! $expression instanceof Expr\MethodCall && ! $expression instanceof Expr\StaticCall) {
            return null;
        }

        $arguments = $this->arguments($method->params, $expression->args, $aliases);
        if ($arguments === null) {
            return null;
        }

        $call = clone $expression;
        $call->args = $arguments;

        return $call;
    }

    /**
     * Resolve a final operation or its returned local result after direct alias assignments.
     */
    private function expression(array $statements): array
    {
        $last = array_pop($statements);
        $expression = $last instanceof Stmt\Return_ || $last instanceof Stmt\Expression ? $last->expr : null;
        $aliases = [];
        foreach ($statements as $index => $statement) {
            $expressionStatement = $statement instanceof Stmt\Expression ? $statement->expr : null;
            $assignment = $this->localAssignment($expressionStatement);
            if ($assignment === null) {
                return [null, []];
            }

            [$target, $value] = $assignment;
            $source = $this->variableName($value);
            if ($source !== null) {
                $aliases[$target] = $aliases[$source] ?? $source;

                continue;
            }

            $isFinalAssignment = $index === array_key_last($statements);
            $isReturnedResult = $expression instanceof Expr\Variable && $expression->name === $target;

            return [$isFinalAssignment && $isReturnedResult ? $value : null, $aliases];
        }

        return [$expression, $aliases];
    }

    /**
     * Transparent preparation can only assign a named local variable.
     *
     * @return array{string, Expr}|null
     */
    private function localAssignment(?Expr $expression): ?array
    {
        if (! $expression instanceof Expr\Assign) {
            return null;
        }

        $target = $this->variableName($expression->var);

        return $target === null ? null : [$target, $expression->expr];
    }

    /**
     * A dynamic variable or computed expression has no stable local alias name.
     */
    private function variableName(Expr $expression): ?string
    {
        return $expression instanceof Expr\Variable && is_string($expression->name) ? $expression->name : null;
    }

    /**
     * Require each incoming argument exactly once in declaration order.
     */
    private function arguments(array $parameters, array $arguments, array $aliases): ?array
    {
        if (count($parameters) !== count($arguments)) {
            return null;
        }

        $normalized = [];
        foreach ($parameters as $index => $parameter) {
            $argument = $arguments[$index];
            if (! $this->isDirectArgument($parameter, $argument)) {
                return null;
            }

            $name = $aliases[$argument->value->name] ?? $argument->value->name;
            if ($name !== $parameter->var->name) {
                return null;
            }

            $copy = clone $argument;
            $copy->value = new Expr\Variable($name);
            $normalized[] = $copy;
        }

        return $normalized;
    }

    /**
     * References and unpacking change argument binding and cannot establish transparent forwarding.
     */
    private function isDirectArgument(Node\Param $parameter, Node $argument): bool
    {
        $isNamedParameter = $parameter->var instanceof Expr\Variable;
        $hasSpecialBinding = $parameter->variadic || $parameter->byRef;
        if (! $isNamedParameter || $hasSpecialBinding || ! $argument instanceof Node\Arg) {
            return false;
        }

        $isNamedValue = $argument->value instanceof Expr\Variable && is_string($argument->value->name);

        return $isNamedValue && ! $argument->unpack && ! $argument->byRef;
    }

    /**
     * Require unchanged arguments and a single direct call; policy or conversion ends a chain.
     */
    public function target(Stmt\ClassMethod $method): ?string
    {
        $call = $this->of($method);
        if ($call === null || ! $call->name instanceof Node\Identifier) {
            return null;
        }

        $receiver = $this->receiver($call);

        return $receiver === null ? null : $receiver.'::'.$call->name->toLowerString();
    }

    /**
     * Distinguish the lexical static receiver from a virtual instance dependency.
     */
    private function receiver(Expr\MethodCall|Expr\StaticCall $call): ?string
    {
        if ($call instanceof Expr\StaticCall) {
            return $this->staticReceiver($call);
        }

        if ($call->var instanceof Expr\Variable && $call->var->name === 'this') {
            return '@self';
        }

        $property = $call->var;
        $isDirectProperty = $property instanceof Expr\PropertyFetch && $property->var instanceof Expr\Variable;
        if (! $isDirectProperty || $property->var->name !== 'this' || ! $property->name instanceof Node\Identifier) {
            return null;
        }

        return '@property:'.$property->name->toString();
    }

    /**
     * Keep relative class names symbolic until the full inheritance graph is available.
     */
    private function staticReceiver(Expr\StaticCall $call): ?string
    {
        if (! $call->class instanceof Node\Name) {
            return null;
        }

        return match (strtolower($call->class->toString())) {
            'self'  => '@scope', 'static' => '@self', 'parent' => '@parent',
            default => strtolower($call->class->toString()),
        };
    }
}
