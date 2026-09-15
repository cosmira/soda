<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Dependencies;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\InheritedParameterContracts;

/**
 * Preserves construction hooks required by declared ancestors or observed dispatch.
 */
final readonly class FactoryHookContracts
{
    /**
     * Use source declarations only; no application class is instantiated or autoloaded.
     */
    public static function forFile(FileFacts $file): self
    {
        $types = InheritedParameterContracts::collect($file->nodes);

        return new self(ExternalParameterContracts::resolve($types, [$file->path]));
    }

    /**
     * Store signature and dispatch facts for the reachable class hierarchy.
     */
    private function __construct(private array $types) {}

    /**
     * A hook requires positive contract evidence, not a conventional method name alone.
     */
    public function isRequired(string $class, string $method): bool
    {
        $class = strtolower($class);
        $method = strtolower($method);
        $pending = [$class];
        $visited = [];
        while ($pending !== []) {
            $name = array_pop($pending);
            if (isset($visited[$name])) {
                continue;
            }

            $visited[$name] = true;
            $type = $this->types[$name] ?? [];
            $methods = $type['methods'] ?? [];
            $signature = $methods[$method] ?? [];
            $isInherited = $name !== $class && ! ($signature['private'] ?? true);
            if ($isInherited || $this->isDispatchMatch($type['factoryDispatch'] ?? [], $method)) {
                return true;
            }

            array_push($pending, ...($type['parents'] ?? []));
        }

        return false;
    }

    /**
     * Match bounded names observed at an actual dynamic call site.
     */
    private function isDispatchMatch(array $patterns, string $method): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $method) === 1) {
                return true;
            }
        }

        return false;
    }
}
