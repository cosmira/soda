<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

/**
 * Resolves ordinary inheritance and trait selections from compact declaration facts.
 */
trait CohesionComposition
{
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
            $hasPrivateState = in_array('private', $base['properties'], true)
                || in_array('private', array_column($base['methods'], 'visibility'), true);
            if ($hasPrivateState) {
                $base['unknown'] = [...$base['unknown'], 'inherited private member identity requires scope resolution'];
            }
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

        $this->surfaces[$name] = $this->mergeSurface($base, $type);

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
            $duplicates = array_intersect_key($base['properties'], $trait['properties']);
            foreach (array_keys($duplicates) as $property) {
                $base['unknown'] = [...$base['unknown'], 'trait property compatibility unresolved '.$property];
            }

            $base['properties'] += $trait['properties'];
            $base['unknown'] = [...$base['unknown'], ...$trait['unknown']];
            foreach ($trait['methods'] as $method => $body) {
                $candidates = $sources[$method] ?? [];
                $candidates[$name] = $body;
                $sources[$method] = $candidates;
            }
        }

        $selected = $this->selectTraits($sources, $use['adaptations']);
        foreach ($selected as $method => $candidates) {
            $isConflict = count($candidates) !== 1 && ! isset($own[$method]);
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
        $duplicates = array_intersect_key($base['properties'], $own['properties']);
        $unknown = [...$base['unknown'], ...$own['unknown']];
        foreach (array_keys($duplicates) as $property) {
            $unknown[] = 'redeclared property '.$property;
        }

        return [
            'methods'    => array_replace($base['methods'], $own['methods']),
            'properties' => array_replace($base['properties'], $own['properties']),
            'unknown'    => $unknown,
        ];
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
            $isAmbiguous = $adaptation['trait'] === null && count($selectedBodies) !== 1;
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
