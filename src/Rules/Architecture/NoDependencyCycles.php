<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\StronglyConnectedComponents;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Prohibits cycles between namespace modules, using actual resolved type dependencies.
 */
final class NoDependencyCycles extends Check
{
    /**
     * @param list<string> $modules Namespace prefixes; without them each exact namespace is a module.
     */
    public function __construct(private readonly array $modules = []) {}

    /**
     * Report mutually dependent modules using resolved local type dependencies.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $classes = [];
        foreach ($project->files as $file => $facts) {
            foreach ($facts['classes'] as $name => $class) {
                $classes[strtolower($name)] = $class + ['file' => $file, 'module' => $this->module($name)];
            }
        }

        $graph = [];
        $locations = [];
        foreach ($classes as $class) {
            $from = $class['module'];
            if ($from === null) {
                continue;
            }

            $targets = $graph[$from] ?? [];
            foreach ($class['depends_on'] ?? [] as $dependency) {
                $targetClass = $classes[strtolower($dependency)] ?? [];
                $to = $targetClass['module'] ?? null;
                if ($to === null || $from === $to) {
                    continue;
                }

                $targets[] = $to;
                $locations[$from] ??= ['file' => $class['file'], 'line' => $class['line']];
            }

            $graph[$from] = array_values(array_unique($targets));
        }

        foreach ((new StronglyConnectedComponents)->collect($graph) as $component) {
            if (count($component) < 2) {
                continue;
            }

            $location = $locations[reset($component)];
            yield new Violation(
                rule: $this->id(), file: $location['file'],
                value: count($component), threshold: 0, line: $location['line'],
                message: 'Mutually dependent modules: '.implode(', ', $component).'. Remove the dependency cycle across module boundaries.',
            );
        }
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
        return ['structure', 'coupling'];
    }
}
