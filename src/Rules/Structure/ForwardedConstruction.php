<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Recognizes a construction result forwarding unchanged inputs through local aliases.
 */
final readonly class ForwardedConstruction
{
    /**
     * Return construction evidence only when the entire method is a transparent factory.
     */
    public static function fromMethod(ClassMethod $method): ?New_
    {
        $creation = self::construction($method);
        if ($method->byRef || ! $creation instanceof New_ || ! self::doesForwardParameters($method, $creation)) {
            return null;
        }

        return $creation;
    }

    /**
     * Resolve a literal concrete class construction in the return statement.
     */
    private static function construction(ClassMethod $method): ?New_
    {
        $statements = $method->stmts ?? [];
        $last = array_pop($statements);
        $returned = $last instanceof Stmt\Return_ ? $last->expr : null;
        $creation = self::returnedExpression($returned, $statements);
        if (! $creation instanceof New_ || ! $creation->class instanceof Name) {
            return null;
        }

        $isRelative = in_array(strtolower($creation->class->toString()), ['self', 'static', 'parent'], true);
        $aliases = self::aliases($statements);
        if ($isRelative || $aliases === null) {
            return null;
        }

        return self::normalize($creation, $aliases);
    }

    /**
     * A returned local result and its direct aliases do not add construction behavior.
     */
    private static function returnedExpression(?Expr $expression, array &$statements): ?Expr
    {
        while ($expression instanceof Expr\Variable && $statements !== []) {
            $last = end($statements);
            $assignment = $last instanceof Stmt\Expression ? $last->expr : null;
            $isLocal = $assignment instanceof Expr\Assign && $assignment->var instanceof Expr\Variable;
            if (! $isLocal || $assignment->var->name !== $expression->name) {
                break;
            }

            array_pop($statements);
            $expression = $assignment->expr;
        }

        return $expression;
    }

    /**
     * Recognize only assignments that preserve an existing variable value.
     */
    private static function aliases(array $statements): ?array
    {
        $aliases = [];
        foreach ($statements as $statement) {
            $assignment = $statement instanceof Stmt\Expression ? $statement->expr : null;
            if (! $assignment instanceof Expr\Assign) {
                return null;
            }

            $isTarget = $assignment->var instanceof Expr\Variable && is_string($assignment->var->name);
            $isSource = $assignment->expr instanceof Expr\Variable && is_string($assignment->expr->name);
            if (! $isTarget || ! $isSource) {
                return null;
            }

            $aliases[$assignment->var->name] = $aliases[$assignment->expr->name] ?? $assignment->expr->name;
        }

        return $aliases;
    }

    /**
     * Normalize argument aliases in a copy, retaining named binding and source metadata.
     */
    private static function normalize(New_ $creation, array $aliases): ?New_
    {
        $normalized = clone $creation;
        $normalized->args = [];
        foreach ($creation->args as $argument) {
            if (! $argument instanceof Node\Arg) {
                return null;
            }

            $copy = clone $argument;
            if ($copy->value instanceof Expr\Variable && is_string($copy->value->name)) {
                $copy->value = new Expr\Variable($aliases[$copy->value->name] ?? $copy->value->name);
            }

            $normalized->args[] = $copy;
        }

        return $normalized;
    }

    /**
     * Compare the parameter and argument sequences without reordering or duplication.
     */
    private static function doesForwardParameters(ClassMethod $method, New_ $creation): bool
    {
        $parameters = array_map(self::parameterName(...), $method->params);
        $arguments = array_map(self::argumentName(...), $creation->args);
        $invalid = in_array(null, $parameters, true) || in_array(null, $arguments, true);

        return ! $invalid && $parameters === $arguments && count(array_unique($parameters)) === count($parameters);
    }

    /**
     * Preserve default-value, reference, variadic and promoted-state contracts.
     */
    private static function parameterName(Node\Param $parameter): ?string
    {
        $hasContract = $parameter->default instanceof Expr || $parameter->byRef || $parameter->variadic;
        $hasMetadata = $parameter->flags !== 0;
        if ($hasContract || $hasMetadata || ! $parameter->var instanceof Expr\Variable) {
            return null;
        }

        return is_string($parameter->var->name) ? $parameter->var->name : null;
    }

    /**
     * Accept an unchanged variable argument, including explicit named binding.
     */
    private static function argumentName(Node\Arg $argument): ?string
    {
        $hasBinding = $argument->unpack || $argument->byRef;
        if ($hasBinding || ! $argument->value instanceof Expr\Variable) {
            return null;
        }

        return is_string($argument->value->name) ? $argument->value->name : null;
    }
}
