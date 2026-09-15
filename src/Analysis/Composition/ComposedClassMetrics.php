<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Composition;

use Cosmira\Soda\Analysis\ProjectFacts;

/**
 * Counts the source and callable surface owned by a class, including nested traits.
 */
final class ComposedClassMetrics
{
    /**
     * Keep legacy declared metrics intact while exposing composition-aware measurements.
     */
    public function collect(ProjectFacts $project): array
    {
        $types = [];
        foreach ($project->files as $file => $facts) {
            foreach ($facts['classes'] as $name => $type) {
                $types[strtolower($name)] = $type + ['name' => $name, 'file' => $file];
            }
        }

        $result = [];
        foreach ($types as $name => $type) {
            if ($type['kind'] !== 'class') {
                continue;
            }

            $surface = $this->surface($name, $types);
            $result[] = [
                'name'    => $type['name'], 'file' => $type['file'], 'line' => $type['line'],
                'methods' => count($surface['methods']),
                'loc'     => array_sum($surface['sources']),
            ];
        }

        return $result;
    }

    /**
     * Deduplicate trait diamonds and overridden method names; aliases add callable names only.
     */
    private function surface(string $name, array $types, array $seen = []): array
    {
        $result = ['methods' => [], 'sources' => []];
        if (isset($seen[$name]) || ! isset($types[$name])) {
            return $result;
        }

        $seen[$name] = true;
        $type = $types[$name];
        $sources = [$name => $type['loc']];
        $methods = [];
        foreach ($type['trait_names'] ?? [] as $trait) {
            $nested = $this->surface(strtolower($trait), $types, $seen);
            $methods += $nested['methods'];
            $sources += $nested['sources'];
        }

        foreach ($type['trait_method_aliases'] ?? [] as $alias => $original) {
            if (isset($methods[$original])) {
                $methods[$alias] = true;
            }
        }

        foreach ($type['concrete_method_names'] ?? [] as $method) {
            $methods[$method] = true;
        }

        return ['methods' => $methods, 'sources' => $sources];
    }
}
