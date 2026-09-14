<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Finds concrete classes that add no behavior or contract around one dependency call.
 *
 * @internal
 */
final readonly class TrivialDelegatingClassAnalyser
{
    /**
     * Stores finder for this analysis instance.
     */
    private NodeFinder $finder;

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private TrivialDelegatingClassPolicy $classPolicy = new TrivialDelegatingClassPolicy,
        private TrivialDelegatingMethodProbe $methodProbe = new TrivialDelegatingMethodProbe,
    ) {
        $this->finder = new NodeFinder;
    }

    /**
     * @param Node[] $nodes
     *
     * @return list<TrivialDelegatingClassFinding>
     */
    public function analyse(array $nodes): array
    {
        $findings = [];

        foreach ($this->finder->findInstanceOf($nodes, Class_::class) as $class) {
            $finding = $this->findingFor($class);

            if ($finding instanceof TrivialDelegatingClassFinding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Build a finding when the class only forwards work to one collaborator.
     */
    private function findingFor(Class_ $class): ?TrivialDelegatingClassFinding
    {
        $method = $this->classPolicy->isContractlessConcrete($class)
            ? $this->singleOperation($class)
            : null;
        $isClassMethod = $method instanceof ClassMethod;
        if (! $isClassMethod) {
            return null;
        }

        $delegate = $this->methodProbe->delegatedProperty($method);
        $hasExtraState = $delegate === null || ! $this->classPolicy->hasOnlyDelegateState($class, $delegate);
        if ($hasExtraState) {
            return null;
        }

        $onlyWiresDelegate = $this->classPolicy->doesConstructorOnlyWireDelegate($class, $delegate);

        if (! $onlyWiresDelegate) {
            return null;
        }

        $name = isset($class->namespacedName)
            ? $class->namespacedName->toString()
            : $class->name?->toString();

        return $name === null ? null : new TrivialDelegatingClassFinding(
            $name,
            $method->name->toString(),
            $delegate,
            $class->getStartLine(),
        );
    }

    /**
     * Identify the sole operation performed by a potential forwarding method.
     */
    private function singleOperation(Class_ $class): ?ClassMethod
    {
        $operation = null;

        foreach ($class->getMethods() as $method) {
            $isConstructor = $method->name->toLowerString() === '__construct';
            if ($isConstructor) {
                continue;
            }

            if ($operation !== null) {
                return null;
            }

            $operation = $method;
        }

        return $operation;
    }
}
