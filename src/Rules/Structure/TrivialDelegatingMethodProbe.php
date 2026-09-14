<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

/**
 * Recognizes a one-statement, same-signature method pass-through.
 *
 * @internal
 */
final class TrivialDelegatingMethodProbe
{
    /**
     * Return the collaborator property used by a direct forwarding call.
     */
    public function delegatedProperty(ClassMethod $method): ?string
    {
        $statement = $this->candidateStatement($method);
        $call = $statement instanceof Stmt ? $this->delegatedCall($statement) : null;
        $isDifferentCall = ! $call instanceof MethodCall
            || ! $call->name instanceof Identifier
            || $call->name->toString() !== $method->name->toString();

        if ($isDifferentCall) {
            return null;
        }

        $forwardsUnchanged = $this->areParametersForwardedUnchanged($method, $call);

        if (! $forwardsUnchanged) {
            return null;
        }

        return $this->propertyName($call->var);
    }

    /**
     * Select the sole executable statement of a potential forwarding method.
     */
    private function candidateStatement(ClassMethod $method): ?Stmt
    {
        $hasExtraBehavior = ! $method->isPublic() || $method->isStatic() || $method->attrGroups !== [];
        if ($hasExtraBehavior) {
            return null;
        }

        return $this->onlyStatement($method->stmts ?? []);
    }

    /**
     * @param list<Stmt> $statements
     */
    private function onlyStatement(array $statements): ?Stmt
    {
        $hasMultipleStatements = count($statements) !== 1;
        if ($hasMultipleStatements) {
            return null;
        }

        foreach ($statements as $statement) {
            return $statement;
        }

        return null;
    }

    /**
     * Find a direct method call in the candidate forwarding operation.
     */
    private function delegatedCall(Stmt $statement): ?MethodCall
    {
        $expression = match (true) {
            $statement instanceof Return_    => $statement->expr,
            $statement instanceof Expression => $statement->expr,
            default                          => null,
        };

        return $expression instanceof MethodCall ? $expression : null;
    }

    /**
     * Check that positional arguments preserve the original parameter names and order.
     */
    private function areParametersForwardedUnchanged(ClassMethod $method, MethodCall $call): bool
    {
        $parameters = array_map($this->parameterName(...), $method->params);
        $arguments = array_map($this->argumentName(...), $call->args);

        return ! in_array(null, $parameters, true)
            && ! in_array(null, $arguments, true)
            && $parameters === $arguments;
    }

    /**
     * Return the statically named variable declared by a parameter.
     */
    private function parameterName(Param $parameter): ?string
    {
        $isNamedParameter = $parameter->var instanceof Variable && is_string($parameter->var->name);

        return $isNamedParameter
            ? $parameter->var->name
            : null;
    }

    /**
     * Return the forwarded variable name, rejecting named, unpacked or computed arguments.
     */
    private function argumentName(Arg $argument): ?string
    {
        $isPositional = ! $argument->name instanceof Identifier
            && ! $argument->unpack
            && $argument->value instanceof Variable
            && is_string($argument->value->name);

        return $isPositional
                ? $argument->value->name
                : null;
    }

    /**
     * Return the name of a direct property access on the current object.
     */
    private function propertyName(Node\Expr $expression): ?string
    {
        $isDynamicProperty = ! $expression instanceof PropertyFetch || ! $expression->name instanceof Identifier;
        if ($isDynamicProperty) {
            return null;
        }

        $isOwnProperty = $expression->var instanceof Variable && $expression->var->name === 'this';

        return $isOwnProperty
            ? $expression->name->toString()
            : null;
    }
}
