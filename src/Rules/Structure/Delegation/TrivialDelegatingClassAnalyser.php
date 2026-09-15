<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure\Delegation;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Finds concrete classes that add no behavior or contract around calls to one dependency.
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
        $isInspectable = $this->classPolicy->isInspectableConcrete($class);
        if (! $isInspectable) {
            return null;
        }

        $methods = $this->operations($class);
        $delegates = array_unique(array_map($this->methodProbe->delegatedProperty(...), $methods));
        $delegate = reset($delegates);
        $isTransparent = count($delegates) === 1 && is_string($delegate)
            && $this->classPolicy->hasOnlyDelegateState($class, $delegate);
        if (! $isTransparent) {
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
            array_map(fn (ClassMethod $method): string => $method->name->toString(), $methods),
            $delegate,
            $class->getStartLine(),
        );
    }

    /**
     * Select every operation; one method with behavior disqualifies the whole class.
     *
     * @return list<ClassMethod>
     */
    private function operations(Class_ $class): array
    {
        return array_values(array_filter(
            $class->getMethods(),
            fn (ClassMethod $method): bool => $method->name->toLowerString() !== '__construct',
        ));
    }
}
