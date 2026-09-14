<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function array_pop;

/**
 * @internal
 */
final class BooleanMethodInheritanceProbe
{
    /**
     * @param array<string, array{inherits: list<string>, methods: array<string, true>}> $typeIndex
     */
    public static function hasDeclaredMethod(array $typeIndex, string $typeName, string $methodName): bool
    {
        $visited = [$typeName => true];
        $typeRow = $typeIndex[$typeName] ?? [];
        $pending = $typeRow['inherits'] ?? [];

        while ($pending !== []) {
            $ancestor = array_pop($pending);
            $isString = is_string($ancestor);
            if (! $isString) {
                continue;
            }

            if (isset($visited[$ancestor])) {
                continue;
            }

            $visited[$ancestor] = true;
            $ancestorType = $typeIndex[$ancestor] ?? null;

            if ($ancestorType === null) {
                continue;
            }

            $methods = $ancestorType['methods'] ?? [];
            if (isset($methods[$methodName])) {
                return true;
            }

            $pending = [...$pending, ...$ancestorType['inherits']];
        }

        return false;
    }
}
