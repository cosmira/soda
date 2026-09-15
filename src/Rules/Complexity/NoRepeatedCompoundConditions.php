<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use InvalidArgumentException;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/** Reports structural repetition without claiming shared domain meaning. */
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
            if ($properties === null || $class->name === null) {
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
        return [];
    }
}
