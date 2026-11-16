<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

/**
 * Keep sibling modules private except for their exported namespaces.
 */
#[Rule(
    label: 'Private module dependencies:',
    advice: "Use the target module's exported namespaces instead of its internals.",
    severity: 'error',
)]
final class ModuleBoundaries extends Check
{
    /**
     * Modules are the immediate children of the configured root namespace.
     *
     * @param list<string> $exports Public namespace prefixes relative to each module.
     * @param list<string> $except  Module names whose internals remain accessible.
     */
    public function __construct(
        /**
         * Root namespace whose immediate children form independent modules.
         */
        private readonly string $namespace,
        /**
         * Namespace prefixes exposed by every module, relative to its root.
         */
        private readonly array $exports,
        /**
         * Target modules temporarily open to unrestricted sibling access.
         */
        private readonly array $except = [],
    ) {
        foreach ([$namespace, ...$exports, ...$except] as $prefix) {
            throw_if(trim($prefix, '\\') === '', InvalidArgumentException::class, 'Module boundary prefixes must not be empty.');
        }
    }

    /**
     * Identify violations of module encapsulation.
     */
    public function id(): string
    {
        return 'module_boundaries';
    }

    /**
     * Use the same resolved type references as the dependency direction checks.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['structure', 'coupling'];
    }

    /**
     * Report each private cross-module reference at its owning declaration.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $dependencies = $file->metrics['typeDependencies'] ?? [];
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, ClassLike::class) as $type) {
            $name = $type->namespacedName?->toString();
            if ($name === null) {
                continue;
            }

            $source = $this->relativeType($name);
            if ($source === null) {
                continue;
            }

            foreach ($dependencies[$name] ?? [] as $dependency) {
                if (! $this->isPrivateDependency($source, $dependency)) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    class: $name, line: $type->getStartLine(),
                    message: sprintf('Private module dependency: %s -> %s. Use an exported namespace of the target module.', $name, $dependency),
                );
            }
        }
    }

    /**
     * Same-module references and explicitly public target namespaces remain accessible.
     */
    private function isPrivateDependency(string $source, string $dependency): bool
    {
        $target = $this->relativeType($dependency);
        if ($target === null) {
            return false;
        }

        [$sourceModule] = explode('\\', $source, 2);
        [$targetModule, $type] = explode('\\', $target, 2);
        if ($sourceModule === $targetModule) {
            return false;
        }

        foreach ($this->except as $module) {
            if ($targetModule === strtolower(trim($module, '\\'))) {
                return false;
            }
        }

        foreach ($this->exports as $export) {
            if (str_starts_with($type, strtolower(trim($export, '\\')).'\\')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip the root without confusing similarly named namespaces or root-level types with modules.
     */
    private function relativeType(string $type): ?string
    {
        $prefix = strtolower(trim($this->namespace, '\\')).'\\';
        $normalized = strtolower(ltrim($type, '\\'));
        if (! str_starts_with($normalized, $prefix)) {
            return null;
        }

        $relative = substr($normalized, strlen($prefix));

        return str_contains($relative, '\\') ? $relative : null;
    }
}
