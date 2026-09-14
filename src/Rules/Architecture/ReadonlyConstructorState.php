<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Recognizes how a constructor stores input as readonly state using resolved parent nodes.
 * The concern owns no state and introduces no collaborator to construct or inject.
 *
 * @internal
 */
trait ReadonlyConstructorState
{
    /**
     * Recognize readonly construction state in promoted and explicit property forms.
     */
    private function isReadonlyState(Node\Param $parameter): bool
    {
        $method = $parameter->getAttribute('parent');
        $isNonConstructor = ! $method instanceof Stmt\ClassMethod || strtolower($method->name->toString()) !== '__construct';
        if ($isNonConstructor) {
            return false;
        }

        if ($parameter->flags === 0) {
            return $this->isAssignedReadonlyState($parameter, $method);
        }

        $class = $method->getAttribute('parent');
        $isReadonly = ($parameter->flags & Stmt\Class_::MODIFIER_READONLY) !== 0
            || ($class instanceof Stmt\Class_ && $class->isReadonly());

        return $isReadonly && $this->isAssignedReadonlyState($parameter, $method);
    }

    /**
     * Allow an explicit parameter only when every reference directly initializes readonly state.
     */
    private function isAssignedReadonlyState(Node\Param $parameter, Stmt\ClassMethod $method): bool
    {
        $isIndirect = $parameter->byRef || $parameter->variadic || ! $parameter->var instanceof Variable;
        if ($isIndirect) {
            return false;
        }

        $references = (new NodeFinder)->find(
            $method->stmts ?? [],
            fn (Node $node): bool => $node instanceof Variable && $node->name === $parameter->var->name,
        );
        $hasState = $references !== [] || $parameter->flags !== 0;
        if (! $hasState) {
            return false;
        }

        foreach ($references as $reference) {
            $isStateAssignment = $this->isReadonlyAssignment($reference, $method);
            if (! $isStateAssignment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Require a direct constructor assignment, not a capture, condition or transformation.
     */
    private function isReadonlyAssignment(Node $reference, Stmt\ClassMethod $method): bool
    {
        $assignment = $reference->getAttribute('parent');
        $isDirect = $assignment instanceof Assign && $assignment->expr === $reference;
        if (! $isDirect) {
            return false;
        }

        $statement = $assignment->getAttribute('parent');
        $isTopLevel = $statement instanceof Stmt\Expression && $statement->getAttribute('parent') === $method;
        if (! $isTopLevel) {
            return false;
        }

        return $this->isReadonlyProperty($assignment->var, $method->getAttribute('parent'));
    }

    /**
     * Resolve an explicitly declared readonly property on the current class.
     */
    private function isReadonlyProperty(Node $target, mixed $class): bool
    {
        $isKnownProperty = $target instanceof PropertyFetch && $target->name instanceof Identifier;
        if (! $isKnownProperty) {
            return false;
        }

        $isOwnProperty = $target->var instanceof Variable && $target->var->name === 'this';
        $isUnknownOwner = ! $isOwnProperty || ! $class instanceof Stmt\Class_;
        if ($isUnknownOwner) {
            return false;
        }

        $property = $class->getProperty($target->name->toString());

        return $property instanceof Stmt\Property && ($property->isReadonly() || $class->isReadonly());
    }
}
