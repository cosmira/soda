<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Rules\Architecture\ModuleBoundaries;
use Illuminate\Support\Collection;

/** Synthetic fact fixtures used to exercise named checks independently of parsing. */
final class CheckFixture
{
    public static function definitions(): array
    {
        static $definitions;
        if ($definitions !== null) {
            return $definitions;
        }
        $definitions = RuleCatalog::definitions();
        $instances = [];
        foreach (RuleCatalog::standard() as $check) {
            $instances[$check::class] = $check;
        }
        $instances[ModuleBoundaries::class] = new ModuleBoundaries(namespace: 'Cosmira\\Soda\\Rules', exports: []);
        foreach ($definitions as &$entry) {
            $reflection = new \ReflectionClass($entry['class']);
            $instance = $instances[$entry['class']] ?? null;
            $arguments = [];
            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                $name = $parameter->getName();
                $arguments[$name] = $instance !== null && $reflection->hasProperty($name)
                    ? $reflection->getProperty($name)->getValue($instance)
                    : ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $entry['default']);
            }
            $entry['arguments'] = $arguments;
        }

        return $definitions;
    }

    public static function standardDefinitions(): array
    {
        $definitions = self::definitions();

        return array_replace(RuleCatalog::standardDefinitions(), array_intersect_key($definitions, RuleCatalog::standardDefinitions()));
    }

    public static function thresholds(): array
    {
        return array_map(static fn (array $entry): int|float => $entry['default'], RuleCatalog::standardDefinitions());
    }

    public static function collect(string $path, array $checks = [], ?array $required = null): array
    {
        $file = (new FactCollector)->collect($path, $required);
        $project = new ProjectFacts;
        $project->add($file);
        $project->resolveClasses();
        $violations = [];
        foreach ($checks as $check) {
            array_push($violations, ...$check->checkFile($file), ...$check->checkProject($project));
        }

        return ['metrics' => $file->metrics, 'violations' => $violations];
    }

    public static function checks(?array $limits = null, array $state = []): array
    {
        $definitions = self::definitions();
        $limits ??= self::thresholds();
        $checks = [];
        foreach ($limits as $id => $limit) {
            $entry = $definitions[$id];
            $class = $entry['class'];
            $args = $entry['arguments'];
            $options = $state['options'][$id] ?? [];
            if (str_starts_with($class, 'Cosmira\\Soda\\Rules\\Design\\')) {
                // Owner diagnostics use several admission minima, not one numeric maximum.
                $args = array_replace($args, $options);
            } elseif (isset($args['max'])) {
                $args = ['min' => $options['min'] ?? $args['min'], 'max' => $options['max'] ?? $limit];
            } elseif (isset($args['minMethods'])) {
                $args['minMethods'] = $limit + 1;
            } elseif ($args !== [] && is_numeric($args[array_key_first($args)])) {
                $args[array_key_first($args)] = $limit;
            }
            if ($id === 'max_layer_dominance_percentage') {
                $args = [$limit, $options['min_files'] ?? 4];
            }
            $checks[] = new $class(...$args);
        }

        return $checks;
    }

    public static function selected(array $checks, array $registered): array
    {
        $named = array_values(array_filter($registered, static fn ($check): bool => $check instanceof Check));
        // Aggregate checker fixtures select limits explicitly; standalone checks use their own configuration.
        if ($checks !== []) {
            return $checks;
        }

        return $named;
    }

    public static function forRule(?Check $rule, array $fixture): Collection
    {
        [$checks, $files] = $fixture;

        return self::run($rule === null ? $checks : [$rule], $files);
    }

    public static function runInput(array $checks, array $fixture): Collection
    {
        [$files, $flow] = $fixture;

        return self::run($checks, $files, $flow);
    }

    public static function run(array $checks, array $files, array $flow = []): Collection
    {
        $violations = [];
        $project = new ProjectFacts();
        foreach ($files as $path => $metrics) {
            $metrics += ['file_loc' => 0, 'classes_count' => 0, 'classes' => [], 'methods' => [], 'namespaces' => []];
            foreach ($metrics['methods'] as $name => &$method) {
                $method += ['complexity' => $flow['complexity'][$name] ?? 1, 'nesting' => $flow['nesting'][$name]['depth'] ?? 0,
                    'nesting_line'       => $flow['nesting'][$name]['line'] ?? null, 'returns' => $flow['returns'][$name] ?? 0,
                    'try_catch'          => $flow['tryCatch'][$name] ?? 0, 'boolean_conditions' => $flow['conditions'][$name] ?? []];
            }
            unset($method);
            $file = is_file($path) ? (new FactCollector)->collect($path) : new FileFacts($path, '', [], $metrics);
            $project->add($file);
            foreach ($checks as $check) {
                array_push($violations, ...$check->checkFile($file));
            }
        }
        $project->resolveClasses();
        foreach ($checks as $check) {
            array_push($violations, ...$check->checkProject($project));
        }

        return collect($violations);
    }
}
