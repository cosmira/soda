<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

/** Resolve PHP method precedence locally to the usage check. */
trait UnusedMethodComposition
{
    /**
     * Cache composed method tables for this project, retaining lexical private identities.
     */
    private function surface(string $name, array $seen = []): array
    {
        if (isset($this->surfaces[$name])) {
            return $this->surfaces[$name];
        }

        $surface = ['methods' => [], 'bodies' => [], 'unknown' => false];
        if (! isset($this->types[$name]) || isset($seen[$name])) {
            $surface['unknown'] = true;

            return $surface;
        }

        $seen[$name] = true;
        $type = $this->types[$name];
        if ($type['parent'] !== null) {
            $surface = $this->surface($type['parent'], $seen);
        }

        $imports = $this->imports($type, $seen);
        $surface['unknown'] = $surface['unknown'] || $imports['unknown'] || $type['duplicate'];
        $methods = $imports['methods'];
        foreach ($methods as &$method) {
            $method['scope'] = $name;
        }

        unset($method);

        $surface['methods'] = array_replace($surface['methods'], $methods, $type['methods']);
        $bodies = $surface['bodies'];
        foreach ($surface['methods'] as $method) {
            $bodies[$method['origin'].'@'.$method['scope']] = $method;
        }

        $surface['bodies'] = $bodies;

        $this->surfaces[$name] = $surface;

        return $surface;
    }

    /**
     * Trait use groups compose independently; unresolved collisions stay explicit.
     */
    private function imports(array $type, array $seen): array
    {
        $result = ['methods' => [], 'unknown' => false];
        foreach ($type['uses'] as $use) {
            $import = $this->traitGroup($use, $seen, $type['methods']);
            $conflicts = array_diff_key(array_intersect_key($result['methods'], $import['methods']), $type['methods']);
            $result['unknown'] = $result['unknown'] || $import['unknown'] || $conflicts !== [];
            $result['methods'] += $import['methods'];
        }

        return $result;
    }

    /**
     * Preserve original sources so an excluded method can still receive an explicit alias.
     */
    private function traitGroup(array $use, array $seen, array $own): array
    {
        $sources = [];
        $result = ['methods' => [], 'unknown' => false];
        foreach ($use['traits'] as $name) {
            $trait = $this->surface($name, $seen);
            $result['unknown'] = $result['unknown'] || $trait['unknown'];
            foreach ($trait['methods'] as $method => $body) {
                $candidates = $sources[$method] ?? [];
                $candidates[$name] = $body;
                $sources[$method] = $candidates;
            }
        }

        $selected = $this->selectTraits($sources, $use['adaptations']);
        $methods = [];
        foreach ($selected as $method => $candidates) {
            if (count($candidates) !== 1) {
                $result['unknown'] = $result['unknown'] || ! isset($own[$method]);

                continue;
            }

            [$methods[$method]] = array_values($candidates);
        }

        $result['methods'] = $methods;

        return $this->aliasTraits($result, $sources, $selected, $use['adaptations']);
    }

    /**
     * Remove only explicitly excluded trait implementations.
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
     * Alias visibility belongs to the composed method, not its original declaration.
     */
    private function aliasTraits(array $result, array $sources, array $selected, array $adaptations): array
    {
        $methods = $result['methods'];
        foreach ($adaptations as $adaptation) {
            if ($adaptation['kind'] !== 'alias') {
                continue;
            }

            $name = $adaptation['method'];
            $candidates = $selected[$name] ?? [];
            if ($adaptation['trait'] !== null) {
                $implementations = $sources[$name] ?? [];
                $body = $implementations[$adaptation['trait']] ?? null;
                $candidates = $body === null ? [] : [$body];
            }

            if (count($candidates) !== 1) {
                $result['unknown'] = true;

                continue;
            }

            [$body] = array_values($candidates);
            $body['visibility'] = $adaptation['visibility'] ?? $body['visibility'];
            $methods[$adaptation['alias'] ?? $name] = $body;
        }

        $result['methods'] = $methods;

        return $result;
    }
}
