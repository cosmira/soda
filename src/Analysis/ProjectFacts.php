<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Rules\Architecture\Cohesion;

/**
 * @phpstan-import-type ClassFacts from FileFacts
 * @phpstan-import-type FileMetrics from FileFacts
 */
final class ProjectFacts
{
    /**
     * @var array<string, FileMetrics>
     */
    public array $files = [];

    /**
     * Retain only scalar/array facts; do not keep the file source or AST.
     */
    public function add(FileFacts $file): void
    {
        $metrics = $file->metrics;
        unset($metrics['compoundVariableNames']);
        $this->files[$file->path] = $metrics;
    }

    /**
     * Resolve inherited and trait-composed surfaces after all files are known.
     */
    public function resolveClasses(): void
    {
        $types = [];
        $interfaceParents = [];
        foreach ($this->files as $data) {
            foreach ($data['classes'] as $name => $declaration) {
                $types[$name] = ($types[$name] ?? []) + $declaration;
            }

            $interfaceParents += $data['interfaceParents'] ?? [];
        }

        foreach ($this->files as $file => $data) {
            $data['classes'] = $this->resolveFileClasses($data['classes'], $types, $interfaceParents);
            $this->files[$file] = $data;
        }

        $this->files = (new Cohesion)->resolve($this->files);
    }

    /**
     * Resolve class surfaces against declarations indexed by their qualified names.
     *
     * @param array<string, ClassFacts>   $classes
     * @param array<string, ClassFacts>   $types
     * @param array<string, list<string>> $interfaceParents
     *
     * @return array<string, ClassFacts>
     */
    private function resolveFileClasses(array $classes, array $types, array $interfaceParents): array
    {
        foreach ($classes as $class => $row) {
            $declaration = $types[$class] ?? [];
            if (array_key_exists('public_method_names', $declaration)) {
                $row['public_methods'] = count($this->effectivePublicMethods($class, $types));
            }

            if (array_key_exists('property_keys', $declaration)) {
                $row['properties'] = count($this->effectivePropertyKeys($class, $types));
            }

            if (array_key_exists('interface_names', $declaration)) {
                $row['interfaces'] = count($this->effectiveInterfaces($class, $types, $interfaceParents));
            }

            $classes[$class] = $row;
        }

        return $classes;
    }

    /**
     * Aggregate namespace counts while retaining the first contributing file.
     *
     * @return array<string, array{count: int, file: string}>
     */
    public function namespaces(): array
    {
        $result = [];
        foreach ($this->files as $file => $data) {
            foreach ($data['namespaces'] as $namespace => $count) {
                $previous = $result[$namespace] ?? ['count' => 0, 'file' => $file];
                $previous['count'] += $count;
                $result[$namespace] = $previous;
            }
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $interfaceParents
     * @param array<string, true>         $seen
     *
     * @return list<string>
     */
    private function interfaceClosure(string $interface, array $interfaceParents, array $seen = []): array
    {
        if (isset($seen[$interface])) {
            return [];
        }

        $seen[$interface] = true;
        $interfaces = [$interface];

        foreach ($interfaceParents[$interface] ?? [] as $parent) {
            array_push($interfaces, ...$this->interfaceClosure($parent, $interfaceParents, $seen));
        }

        return array_values(array_unique($interfaces));
    }

    /**
     * @param array<string, ClassFacts> $types
     * @param array<string, true>       $seen
     *
     * @return list<string>
     */
    private function effectivePublicMethods(
        string $type,
        array $types,
        array $seen = [],
    ): array {
        if (isset($seen[$type])) {
            return [];
        }

        $seen[$type] = true;
        $declaration = $types[$type] ?? [];
        $ownMethods = $declaration['public_method_names'] ?? [];
        $traitMethods = [];

        $parent = $declaration['parent'] ?? null;
        $inheritedMethods = $parent === null
            ? []
            : $this->effectivePublicMethods($parent, $types, $seen);

        foreach ($declaration['trait_names'] ?? [] as $trait) {
            array_push($traitMethods, ...$this->effectivePublicMethods(
                $trait,
                $types,
                $seen,
            ));
        }

        $traitMethods = array_diff($traitMethods, $declaration['hidden_trait_methods'] ?? []);
        $methods = [...$inheritedMethods, ...$traitMethods, ...$ownMethods, ...($declaration['public_trait_aliases'] ?? [])];

        return array_values(array_unique($methods));
    }

    /**
     * @param array<string, ClassFacts> $types
     * @param array<string, true>       $seen
     *
     * @return list<string>
     */
    private function effectivePropertyKeys(
        string $type,
        array $types,
        array $seen = [],
    ): array {
        if (isset($seen[$type])) {
            return [];
        }

        $seen[$type] = true;
        $declaration = $types[$type] ?? [];
        $keys = $declaration['property_keys'] ?? [];
        $parent = $declaration['parent'] ?? null;

        $ancestors = [...($parent === null ? [] : [$parent]), ...($declaration['trait_names'] ?? [])];

        foreach ($ancestors as $ancestor) {
            array_push($keys, ...$this->effectivePropertyKeys(
                $ancestor,
                $types,
                $seen,
            ));
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, ClassFacts>   $types
     * @param array<string, list<string>> $interfaceParents
     * @param array<string, true>         $seenClasses
     *
     * @return list<string>
     */
    private function effectiveInterfaces(
        string $class,
        array $types,
        array $interfaceParents,
        array $seenClasses = [],
    ): array {
        if (isset($seenClasses[$class])) {
            return [];
        }

        $seenClasses[$class] = true;
        $interfaces = [];

        $declaration = $types[$class] ?? [];
        foreach ($declaration['interface_names'] ?? [] as $interface) {
            array_push($interfaces, ...$this->interfaceClosure($interface, $interfaceParents));
        }

        $parent = $declaration['parent'] ?? null;
        if ($parent !== null) {
            array_push($interfaces, ...$this->effectiveInterfaces(
                $parent,
                $types,
                $interfaceParents,
                $seenClasses,
            ));
        }

        return array_values(array_unique($interfaces));
    }
}
