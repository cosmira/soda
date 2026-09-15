<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure\Delegation;

use Cosmira\Soda\Analysis\ForwardedCall;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;

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
        $call = (new ForwardedCall)->of($method);
        if (! $call instanceof MethodCall || ! $call->name instanceof Identifier || $method->isStatic()) {
            return null;
        }

        return $this->propertyName($call->var);
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
