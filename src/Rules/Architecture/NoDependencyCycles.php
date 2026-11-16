<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\Design\DesignEvidence;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\StronglyConnectedComponents;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Prohibits cycles between namespace modules, using actual resolved type dependencies.
 */
#[Rule(
    label: 'Module dependency cycles:',
    advice: 'Remove mutual module dependencies and mandatory constructor cycles; simplify collaboration rather than hiding a cycle with another layer.',
    severity: 'error',
)]
final class NoDependencyCycles extends Check
{
    /**
     * Configure the namespace prefixes that define dependency module boundaries.
     *
     * @param list<string> $modules Namespace prefixes; without them each exact namespace is a module.
     */
    public function __construct(
        /**
         * Namespace prefixes used to group class dependencies into modules.
         */
        private readonly array $modules = [],
    ) {}

    /**
     * Report mutually dependent modules using resolved local type dependencies.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $classes = $this->classes($project);
        $dependencies = $this->dependencies($classes);
        foreach ((new StronglyConnectedComponents)->collect($dependencies['graph']) as $component) {
            if (count($component) >= 2) {
                yield $this->cycleViolation($component, $dependencies);
            }
        }

        yield from $this->constructorCycles($project);
    }

    /**
     * Required constructor dependencies can make ordinary construction cyclic within one module.
     *
     * @return iterable<Violation>
     */
    private function constructorCycles(ProjectFacts $project): iterable
    {
        $types = array_filter(DesignEvidence::types($project->files), fn (array $type): bool => ($type['construction'] ?? null) !== null && $this->module($type['name']) !== null);
        $dependencies = $this->constructorDependencies($types);
        $graph = $dependencies['graph'];
        foreach ((new StronglyConnectedComponents)->collect($graph) as $component) {
            $modules = array_unique(array_map(function (string $name) use ($types): ?string {
                $type = $types[$name];

                return $this->module($type['name']);
            }, $component));
            if (count($modules) !== 1) {
                continue;
            }

            $first = $component[0];
            if (count($component) === 1 && ! in_array($first, $graph[$first], true)) {
                continue;
            }

            yield $this->constructorViolation($component, $dependencies, $types);
        }
    }

    /**
     * Only targets with their own exact construction facts become graph vertices and edges.
     */
    private function constructorDependencies(array $types): array
    {
        $graph = [];
        $edges = [];
        foreach ($types as $name => $type) {
            $targets = [];
            $proofs = [];
            foreach ($type['construction'] as $dependency) {
                $target = $dependency['target'];
                if (! isset($types[$target])) {
                    continue;
                }

                $targets[] = $target;
                $proofs[$target] ??= $dependency + ['file' => $type['file'], 'source' => $type['name']];
            }

            $graph[$name] = array_values(array_unique($targets));
            $edges[$name] = $proofs;
        }

        return ['graph' => $graph, 'edges' => $edges];
    }

    /**
     * Explain the parameter forcing each participant to be constructed before its predecessor.
     */
    private function constructorViolation(array $component, array $dependencies, array $types): Violation
    {
        $cycle = StronglyConnectedComponents::cyclePath($dependencies['graph'], $component);
        $edges = $dependencies['edges'];
        $proofs = $edges[$cycle[0]];
        $first = $proofs[$cycle[1]];
        $evidence = [];
        foreach (array_slice($cycle, 1) as $index => $target) {
            $proofs = $edges[$cycle[$index]];
            $edge = $proofs[$target];
            $type = $types[$target];
            $evidence[] = $edge['source'].'::__construct($'.$edge['parameter'].') -> '.$type['name'].' ('.$edge['file'].':'.$edge['line'].')';
        }

        return new Violation(
            rule: $this->id(), file: $first['file'],
            value: count($component), threshold: 0, line: $first['line'],
            message: 'Constructor dependency cycle: '.implode('; ', $evidence)
                .'. Each exact type requires an existing instance of the next; ordinary construction has no starting point.'
                .' Review whether these collaborators should be one object or whether the reverse dependency is unnecessary.'
                .' Do not add a service locator, nullable test seam or an interface solely to hide the cycle.',
        );
    }

    /**
     * Attach module ownership and declaration locations to resolved project types.
     */
    private function classes(ProjectFacts $project): array
    {
        $classes = [];
        foreach ($project->files as $file => $facts) {
            foreach ($facts['classes'] as $name => $class) {
                $classes[strtolower($name)] = $class + ['file' => $file, 'module' => $this->module($name), 'name' => $name];
            }
        }

        return $classes;
    }

    /**
     * Build module edges and retain one concrete type dependency proving each edge.
     */
    private function dependencies(array $classes): array
    {
        $graph = [];
        $edges = [];
        foreach ($classes as $class) {
            $from = $class['module'];
            if ($from === null) {
                continue;
            }

            $targets = $graph[$from] ?? [];
            $proofs = $edges[$from] ?? [];
            foreach ($class['depends_on'] ?? [] as $dependency) {
                $targetClass = $classes[strtolower($dependency)] ?? [];
                $to = $targetClass['module'] ?? null;
                if ($to === null || $from === $to) {
                    continue;
                }

                $targets[] = $to;
                $proofs[$to] ??= ['file' => $class['file'], 'line' => $class['line'], 'source' => $class['name'], 'target' => $dependency];
            }

            $graph[$from] = array_values(array_unique($targets));
            $edges[$from] = $proofs;
        }

        return ['graph' => $graph, 'edges' => $edges];
    }

    /**
     * Explain one real closed path while retaining the size of the complete component.
     */
    private function cycleViolation(array $component, array $dependencies): Violation
    {
        $cycle = StronglyConnectedComponents::cyclePath($dependencies['graph'], $component);
        $edges = $dependencies['edges'];
        $proofs = $edges[$cycle[0]];
        $location = $proofs[$cycle[1]];
        $evidence = [];
        foreach (array_slice($cycle, 1) as $index => $target) {
            $proofs = $edges[$cycle[$index]];
            $edge = $proofs[$target];
            $evidence[] = $edge['source'].' -> '.$edge['target'].' ('.$edge['file'].':'.$edge['line'].')';
        }

        return new Violation(
            rule: $this->id(), file: $location['file'],
            value: count($component), threshold: 0, line: $location['line'],
            message: 'Mutually dependent modules: '.implode(', ', $component)
                .'. Cycle: '.implode(' -> ', $cycle).'. Edges: '.implode('; ', $evidence)
                .'. Remove the dependency cycle across module boundaries.',
        );
    }

    /**
     * Select the longest configured namespace prefix, or the exact namespace by default.
     */
    private function module(string $class): ?string
    {
        if ($this->modules === []) {
            $separator = strrpos($class, '\\');

            return $separator === false ? '<global>' : strtolower(substr($class, 0, $separator));
        }

        $match = null;
        $qualified = strtolower($class);
        foreach ($this->modules as $module) {
            $prefix = strtolower(trim($module, '\\'));
            $isMoreSpecific = $match === null || strlen($prefix) > strlen($match);
            $namespacePrefix = $prefix.'\\';
            if (str_starts_with($qualified, $namespacePrefix) && $isMoreSpecific) {
                $match = $prefix;
            }
        }

        return $match;
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_dependency_cycles';
    }

    /**
     * Declare the fact collectors required by this check.
     */
    public function requiredAnalyses(): array
    {
        return ['structure', 'coupling', 'ownerDesign'];
    }
}
