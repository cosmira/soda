<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\Control\TypeDispatchAnalysis;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Repeated resolution of the same type family belongs behind one operation boundary.
 */
final class NoRepeatedTypeDispatch extends Check
{
    /**
     * Require repetition in distinct methods, never a count of tests inside one method.
     */
    public function __construct(private readonly int $minMethods = 2)
    {
        throw_if($minMethods < 2, \InvalidArgumentException::class, 'minMethods must be at least 2.');
    }

    /**
     * Group identical type sets within each named class and retain all contributing methods.
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\Class_::class) as $class) {
            if ($class->name === null || $class->extends !== null || $class->getTraitUses() !== []) {
                continue;
            }

            $groups = [];
            foreach ($class->getMethods() as $method) {
                foreach ((new TypeDispatchAnalysis)->decisions($method) as $types) {
                    $key = serialize($types);
                    $group = $groups[$key] ?? ['types' => $types, 'methods' => []];
                    $methods = $group['methods'];
                    $methods[$method->name->toLowerString()] = ['name' => $method->name->toString(), 'line' => $method->getStartLine()];
                    $group['methods'] = $methods;
                    $groups[$key] = $group;
                }
            }

            foreach ($groups as $group) {
                $methods = $group['methods'];
                if (count($methods) < $this->minMethods) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: count($methods), threshold: $this->minMethods - 1,
                    class: $class->namespacedName?->toString() ?? $class->name->toString(),
                    line: min(array_column($methods, 'line')),
                    message: sprintf(
                        'Type/discriminator set %s is dispatched in methods %s. Centralize the type-specific operation instead of repeating the dispatch.',
                        implode(', ', $group['types']), implode(', ', array_column($methods, 'name')),
                    ),
                );
            }
        }
    }

    /**
     * Count independent implementations after trait selection and inheritance have resolved.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        foreach ((new BehaviorComposition)->project($project) as $type) {
            $isComposed = $type['parent'] !== null || $type['uses'] !== [];
            if ($type['kind'] !== 'class' || ! $isComposed) {
                continue;
            }

            $groups = [];
            foreach ($type['methods'] as $method) {
                foreach ($method['dispatches'] as $family) {
                    $key = serialize($family);
                    $group = $groups[$key] ?? ['family' => $family, 'methods' => []];
                    $methods = $group['methods'];
                    $methods[$method['origin']] = $method['name'];
                    $group['methods'] = $methods;
                    $groups[$key] = $group;
                }
            }

            foreach ($groups as $group) {
                if (count($group['methods']) < $this->minMethods) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $type['file'], value: count($group['methods']), threshold: $this->minMethods - 1,
                    class: $type['name'], line: $type['line'],
                    message: 'Type/discriminator set '.implode(', ', $group['family']).' is repeated in composed methods '.implode(', ', $group['methods']).'. Centralize the type-specific operation.',
                );
            }
        }
    }

    /**
     * Return the stable rule identifier.
     */
    public function id(): string
    {
        return 'no_repeated_type_dispatch';
    }

    /**
     * Reuse resolved syntax without additional metrics.
     */
    public function requiredAnalyses(): array
    {
        return ['classBehavior'];
    }
}
