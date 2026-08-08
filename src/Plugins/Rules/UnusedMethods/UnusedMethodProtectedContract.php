<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\UnusedMethods;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @internal Whether a protected method is part of an in-file abstract/interface contract.
 */
final readonly class UnusedMethodProtectedContract
{
    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    public static function isDeclaredOnSupertype(ClassMethod $method, Class_ $class, array $byName): bool
    {
        if (self::hasPhpOverrideAttribute($method)) {
            return true;
        }

        $name = $method->name->toString();

        if (self::isNamedInAbstractAncestors($class, $byName, $name)) {
            return true;
        }

        return self::isNamedInImplementedInterfaces($class, $byName, $name);
    }

    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    private static function isNamedInAbstractAncestors(Class_ $class, array $byName, string $name): bool
    {
        foreach (UnusedMethodInFileInheritance::ancestors($class, $byName) as $ancestor) {
            if ($ancestor->isAbstract() && UnusedMethodInFileInheritance::hasMethod($ancestor, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    private static function isNamedInImplementedInterfaces(Class_ $class, array $byName, string $name): bool
    {
        foreach ($class->implements as $impl) {
            if (! $impl instanceof Name) {
                continue;
            }

            $iface = $byName[$impl->getLast()] ?? null;
            if ($iface instanceof Interface_ && UnusedMethodInFileInheritance::hasMethod($iface, $name)) {
                return true;
            }
        }

        return false;
    }

    private static function hasPhpOverrideAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $n = $attr->name;
                if ($n instanceof Name && $n->getLast() === 'Override') {
                    return true;
                }
            }
        }

        return false;
    }
}
