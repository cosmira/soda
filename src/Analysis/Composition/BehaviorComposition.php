<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Composition;

use Cosmira\Soda\Analysis\ProjectFacts;

/**
 * Resolves ordinary inheritance and trait selections from compact declaration facts.
 */
final class BehaviorComposition
{
    /**
     * Resolved type surfaces cached only within the current project.
     */
    private array $surfaces = [];

    /**
     * Attach declaration provenance once before composing a complete analysed project.
     */
    public function project(ProjectFacts $project): array
    {
        $types = [];
        foreach ($project->files as $file => $facts) {
            foreach ($facts['classBehavior'] ?? [] as $name => $type) {
                $methods = $type['methods'];
                foreach ($methods as &$method) {
                    $method['file'] = $file;
                }

                unset($method);
                $type['methods'] = $methods;
                $types[$name] = $type + ['file' => $file];
            }
        }

        return $this->resolve($types);
    }

    /**
     * Resolve the supplied declarations without retaining ASTs or state across projects.
     */
    public function resolve(array $types): array
    {
        $this->surfaces = [];
        $result = [];
        foreach ($types as $name => $type) {
            $result[$name] = $this->surface($name, $types) + $type;
        }

        return $result;
    }

    /**
     * Compose only known declarations; missing/cyclic relationships remain explicit unknowns.
     */
    private function surface(string $name, array $types, array $seen = []): array
    {
        if (isset($this->surfaces[$name])) {
            return $this->surfaces[$name];
        }

        $isMissing = ! isset($types[$name]) || isset($seen[$name]);
        if ($isMissing) {
            return ['methods' => [], 'properties' => [], 'unknown' => ['unresolved or cyclic type '.$name]];
        }

        $seen[$name] = true;
        $type = $types[$name];
        $base = ['methods' => [], 'properties' => [], 'unknown' => $type['unknown']];
        if ($type['parent'] !== null) {
            $base = $this->surface(strtolower($type['parent']), $types, $seen);

        }

        $imported = [];
        foreach ($type['uses'] as $use) {
            $import = $this->traitSurface($use, $types, $seen, $type['methods']);
            $conflicts = array_diff_key(array_intersect_key($imported, $import['methods']), $type['methods']);
            foreach (array_keys($conflicts) as $method) {
                $import['unknown'] = [...$import['unknown'], 'trait conflict across use groups '.$method];
            }

            $imported += $import['methods'];
            $base = $this->mergeSurface($base, $import);
        }

        $surface = $this->mergeSurface($base, $type);
        if ($type['kind'] === 'class') {
            $surface = $this->bindScope($surface, $name, $types);
        }

        $this->surfaces[$name] = $surface;

        return $this->surfaces[$name];
    }

    /**
     * Resolve one use group, preserving excluded implementations for explicit aliases.
     */
    private function traitSurface(array $use, array $types, array $seen, array $own): array
    {
        $sources = [];
        $base = ['methods' => [], 'properties' => [], 'unknown' => []];
        foreach ($use['traits'] as $name) {
            $trait = $this->surface($name, $types, $seen);
            $ambiguous = $base['ambiguousProperties'] ?? [];
            foreach ($this->redeclarations($base, $trait) as $property) {
                $base['unknown'] = [...$base['unknown'], 'trait property compatibility unresolved '.$property];
                $ambiguous[$property] = true;
            }

            $base['ambiguousProperties'] = $ambiguous;
            $base['properties'] += $trait['properties'];
            $base['propertyTypes'] = ($base['propertyTypes'] ?? []) + ($trait['propertyTypes'] ?? []);
            $base['blockedReads'] = ($base['blockedReads'] ?? []) + ($trait['blockedReads'] ?? []);
            $base['propertyOrigins'] = ($base['propertyOrigins'] ?? []) + ($trait['propertyOrigins'] ?? []);
            $base['ambiguousProperties'] = ($base['ambiguousProperties'] ?? []) + ($trait['ambiguousProperties'] ?? []);
            $base['unknown'] = [...$base['unknown'], ...$trait['unknown']];
            foreach ($trait['methods'] as $method => $body) {
                $candidates = $sources[$method] ?? [];
                $candidates[$name] = $body;
                $sources[$method] = $candidates;
            }
        }

        $selected = $this->selectTraits($sources, $use['adaptations']);
        foreach ($selected as $method => $candidates) {
            $origins = array_unique(array_column($candidates, 'origin'));
            $isConflict = count($origins) !== 1 && ! isset($own[$method]);
            if ($isConflict) {
                $base['unknown'] = [...$base['unknown'], 'trait method conflict '.$method];

                continue;
            }

            $body = reset($candidates);
            if (is_array($body)) {
                $methods = $base['methods'];
                $methods[$method] = $body;
                $base['methods'] = $methods;
            }
        }

        return $this->aliasTraits($base, $sources, $selected, $use['adaptations']);
    }

    /**
     * A property redeclaration can alter identity/visibility and is not silently flattened.
     */
    private function mergeSurface(array $base, array $own): array
    {
        $unknown = [...$base['unknown'], ...$own['unknown']];
        $ambiguous = ($base['ambiguousProperties'] ?? []) + ($own['ambiguousProperties'] ?? []);
        foreach ($this->redeclarations($base, $own) as $property) {
            $unknown[] = 'redeclared property '.$property;
            $ambiguous[$property] = true;
        }

        return [
            'contracts'           => array_values(array_unique([...($base['contracts'] ?? []), ...($own['contracts'] ?? [])])),
            'propertyTypes'       => array_replace($base['propertyTypes'] ?? [], $own['propertyTypes'] ?? []),
            'blockedReads'        => ($base['blockedReads'] ?? []) + ($own['blockedReads'] ?? []),
            'ambiguousProperties' => $ambiguous,
            'propertyOrigins'     => array_replace($base['propertyOrigins'] ?? [], $own['propertyOrigins'] ?? []),
            'methods'             => array_replace($base['methods'], $own['methods']),
            'properties'          => array_replace($base['properties'], $own['properties']),
            'unknown'             => $unknown,
        ];
    }

    /**
     * Bind imported trait state and method bodies to the consuming class's lexical scope.
     */
    private function bindScope(array $surface, string $name, array $types): array
    {
        $methods = $surface['methods'];
        foreach ($methods as &$method) {
            $method['scope'] ??= $name;
        }

        unset($method);
        $origins = $surface['propertyOrigins'];
        foreach ($origins as $property => $origin) {
            $declaration = $types[$origin] ?? [];
            if (($declaration['kind'] ?? null) === 'trait') {
                $origins[$property] = $name;
            }
        }

        return array_replace($surface, ['methods' => $methods, 'propertyOrigins' => $origins]);
    }

    /**
     * A diamond imports the same declaration once; independent state declarations need review.
     */
    private function redeclarations(array $base, array $own): array
    {
        $duplicates = array_intersect_key($base['properties'], $own['properties']);
        $baseOrigins = $base['propertyOrigins'] ?? [];
        $ownOrigins = $own['propertyOrigins'] ?? [];
        $conflicts = [];
        foreach (array_keys($duplicates) as $property) {
            if (($baseOrigins[$property] ?? null) !== ($ownOrigins[$property] ?? null)) {
                $conflicts[] = $property;
            }
        }

        return $conflicts;
    }

    /**
     * Apply insteadof without treating a conflict as absent behavior.
     */
    private function selectTraits(array $sources, array $adaptations): array
    {
        foreach ($adaptations as $adaptation) {
            if ($adaptation['kind'] !== 'precedence') {
                continue;
            }

            $method = $adaptation['method'];
            $candidates = $sources[$method] ?? [];
            foreach ($adaptation['instead'] as $excluded) {
                unset($candidates[$excluded]);
            }

            $sources[$method] = $candidates;
        }

        return $sources;
    }

    /**
     * Copy the explicitly selected implementation and apply alias visibility locally.
     */
    private function aliasTraits(array $base, array $sources, array $selected, array $adaptations): array
    {
        $methods = $base['methods'];
        foreach ($adaptations as $adaptation) {
            if ($adaptation['kind'] !== 'alias') {
                continue;
            }

            $method = $adaptation['method'];
            $candidates = $sources[$method] ?? [];
            $selectedBodies = $selected[$method] ?? [];
            $isAmbiguous = $adaptation['trait'] === null && count(array_unique(array_column($selectedBodies, 'origin'))) !== 1;
            if ($isAmbiguous) {
                $base['unknown'] = [...$base['unknown'], 'ambiguous trait alias '.$method];

                continue;
            }

            $body = $adaptation['trait'] === null ? reset($selectedBodies) : ($candidates[$adaptation['trait']] ?? null);
            $isBody = is_array($body);
            if (! $isBody) {
                $base['unknown'] = [...$base['unknown'], 'unresolved trait alias '.$method];

                continue;
            }

            $alias = $adaptation['alias'] ?? $method;
            $body['name'] = $alias;
            $body['visibility'] = $adaptation['visibility'] ?? $body['visibility'];
            $methods[$alias] = $body;
        }

        $base['methods'] = $methods;

        return $base;
    }
}
