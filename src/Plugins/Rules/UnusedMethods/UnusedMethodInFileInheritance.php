<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\UnusedMethods;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @internal Class/interface hierarchy within a single parsed file.
 */
final class UnusedMethodInFileInheritance
{
    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     *
     * @return list<Class_>
     */
    public static function ancestors(Class_ $class, array $byName): array
    {
        $out = [];
        $current = $class;

        while (true) {
            $parent = self::directParent($current, $byName);
            if (! $parent instanceof Class_) {
                return $out;
            }

            $out[] = $parent;
            $current = $parent;
        }
    }

    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    public static function directParent(Class_ $class, array $byName): ?Class_
    {
        if (! $class->extends instanceof Name) {
            return null;
        }

        $parent = $byName[$class->extends->getLast()] ?? null;

        return $parent instanceof Class_ ? $parent : null;
    }

    public static function hasMethod(Class_|Interface_ $type, string $name): bool
    {
        foreach ($type->stmts ?? [] as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->toString() === $name) {
                return true;
            }
        }

        return false;
    }
}
