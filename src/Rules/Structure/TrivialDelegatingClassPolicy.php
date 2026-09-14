<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;

/**
 * Excludes small classes that carry a contract, boundary marker, state, or constructor behavior.
 *
 * @internal
 */
final class TrivialDelegatingClassPolicy
{
    /**
     * Determine whether contractless concrete applies to the supplied input.
     */
    public function isContractlessConcrete(Class_ $class): bool
    {
        return $class->name instanceof Identifier
            && ! $class->isAbstract()
            && ! $class->extends instanceof Name
            && $class->implements === []
            && $class->getTraitUses() === []
            && $class->attrGroups === []
            && $class->getConstants() === [];
    }

    /**
     * Check the supplied input for only delegate state.
     */
    public function hasOnlyDelegateState(Class_ $class, string $delegate): bool
    {
        return $this->propertyNames($class) === [$delegate];
    }

    /**
     * Check that construction only assigns the delegated collaborator.
     */
    public function doesConstructorOnlyWireDelegate(Class_ $class, string $delegate): bool
    {
        $statements = $class->getMethod('__construct')?->stmts ?? [];

        if ($statements === []) {
            return true;
        }

        $statement = $this->onlyStatement($statements);

        return $statement instanceof Expression
            && $this->isDelegateAssignment($statement->expr, $delegate);
    }

    /**
     * @return list<string>
     */
    private function propertyNames(Class_ $class): array
    {
        $properties = [];

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $item) {
                $properties[$item->name->toString()] = true;
            }
        }

        foreach ($class->getMethod('__construct')?->params ?? [] as $parameter) {
            $isPromotedName = $parameter->flags !== 0 && $parameter->var instanceof Variable && is_string($parameter->var->name);
            if ($isPromotedName) {
                $properties[$parameter->var->name] = true;
            }
        }

        return array_keys($properties);
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
     * Determine whether delegate assignment applies to the supplied input.
     */
    private function isDelegateAssignment(mixed $expression, string $delegate): bool
    {
        return $expression instanceof Assign
            && $this->propertyName($expression->var) === $delegate
            && $expression->expr instanceof Variable
            && is_string($expression->expr->name);
    }

    /**
     * Return the name of a direct property access on the current object.
     */
    private function propertyName(mixed $expression): ?string
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
