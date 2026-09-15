<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use InvalidArgumentException;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Reports structural repetition without claiming shared domain meaning.
 */
final class NoRepeatedCompoundConditions extends Check
{
    /**
     * Set the minimum number of distinct methods sharing a condition.
     */
    public function __construct(private readonly int $minMethods = 3)
    {
        throw_if($minMethods < 2, InvalidArgumentException::class, 'minMethods must be at least 2.');
    }

    /**
     * Report one finding per condition group in each eligible class.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $scope = new CompoundConditionScope;
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Class_::class) as $class) {
            $properties = $scope->properties($class);
            if ($properties === null || $class->name === null || $class->extends !== null || $class->getTraitUses() !== []) {
                continue;
            }

            foreach ($this->groups($class, $properties, $scope) as $methods) {
                if (count($methods) >= $this->minMethods) {
                    yield $this->violation($file->path, $class, $methods);
                }
            }
        }
    }

    /**
     * Include conditions supplied by known parent classes and traits across source files.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $resolved = (new BehaviorComposition)->project($project);
        foreach ($resolved as $type) {
            $isComposed = $type['parent'] !== null || $type['uses'] !== [];
            if ($type['kind'] !== 'class' || ! $isComposed) {
                continue;
            }

            $groups = $this->composedGroups($type, $resolved);
            foreach ($groups as $methods) {
                if (count($methods) < $this->minMethods) {
                    continue;
                }

                $first = reset($methods);
                $locations = array_map(fn (array $method): string => $method['name'].'() at '.$method['file'].':'.$method['line'], $methods);
                yield new Violation(
                    rule: $this->id(), file: $first['file'], value: count($methods), threshold: $this->minMethods - 1,
                    class: $type['name'], line: $first['line'],
                    message: 'The same compound condition appears in '.count($methods).' composed methods: '.implode('; ', $locations).'. Extract a predicate if these express one decision.',
                );
            }
        }
    }

    /**
     * Group composed method decisions by their expression and resolved lexical bindings.
     */
    private function composedGroups(array $type, array $resolved): array
    {
        $groups = [];
        foreach ($type['methods'] as $method) {
            foreach ($method['conditions'] as $condition) {
                $bindings = $this->propertyBindings($condition['properties'], $method, $type, $resolved);
                if ($bindings === null) {
                    continue;
                }

                // An alias exposes one body twice; it is not a second independent decision.
                foreach ($condition['relativeConstants'] ?? [] as $owner) {
                    $bindings['constant:'.$owner] = $owner === 'static' ? strtolower($type['name']) : $method['scope'];
                }

                $key = $condition['key'].serialize($bindings);
                $occurrences = $groups[$key] ?? [];
                $occurrences[$method['origin']] = [
                    'name' => $method['name'], 'file' => $method['file'], 'line' => $condition['line'],
                ];
                $groups[$key] = $occurrences;
            }
        }

        return $groups;
    }

    /**
     * Bind private reads to their lexical owner and other reads to the effective receiver.
     */
    private function propertyBindings(array $properties, array $method, array $type, array $resolved): ?array
    {
        $scope = $method['scope'] ?? strtolower($type['name']);
        $lexical = $resolved[$scope] ?? $type;
        $bindings = [];
        $lexicalProperties = $lexical['properties'];
        $lexicalOrigins = $lexical['propertyOrigins'];
        foreach ($properties as $property) {
            $private = ($lexicalProperties[$property] ?? null) === 'private'
                && ($lexicalOrigins[$property] ?? null) === $scope;
            $owner = $private ? $lexical : $type;
            $ownerProperties = $owner['properties'];
            $blockedReads = $owner['blockedReads'];
            $ownerOrigins = $owner['propertyOrigins'];
            if (! isset($ownerProperties[$property]) || isset($blockedReads[$property])) {
                return null;
            }

            $bindings[$property] = $ownerOrigins[$property];
        }

        ksort($bindings);

        return $bindings;
    }

    /**
     * Group exact condition fingerprints by distinct owning method.
     */
    private function groups(Stmt\Class_ $class, array $properties, CompoundConditionScope $scope): array
    {
        $syntax = new CompoundConditionFingerprint;
        $groups = [];
        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();
            foreach ($scope->conditions($method->stmts ?? []) as $condition) {
                $key = $syntax->encode($condition, $properties);
                if ($key === null || ! $syntax->hasBooleanOperator($condition)) {
                    continue;
                }

                $methods = $groups[$key] ?? [];
                $positions = $methods[$name] ?? [];
                $positions[] = $condition->getStartLine();
                $methods[$name] = $positions;
                $groups[$key] = $methods;
            }
        }

        return $groups;
    }

    /**
     * Preserve all locations and the maximum permitted method count in diagnostics.
     */
    private function violation(string $file, Stmt\Class_ $class, array $methods): Violation
    {
        $locations = [];
        $lines = [];
        foreach ($methods as $method => $positions) {
            $locations[] = $method.'() at lines '.implode(', ', array_unique($positions));
            array_push($lines, ...$positions);
        }

        return new Violation(
            rule: $this->id(), file: $file, value: count($methods), threshold: $this->minMethods - 1,
            class: isset($class->namespacedName) ? $class->namespacedName->toString() : $class->name->toString(),
            line: min($lines),
            message: sprintf('The same compound condition appears in %d methods: %s. Extract a named predicate if these occurrences express the same decision.', count($methods), implode('; ', $locations)),
        );
    }

    /**
     * Identify this standard check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_repeated_compound_conditions';
    }

    /**
     * Reuse the parsed AST without collecting metrics.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['classBehavior'];
    }
}
