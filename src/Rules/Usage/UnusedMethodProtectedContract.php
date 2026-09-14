<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

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
        foreach (self::ancestors($class, $byName) as $ancestor) {
            $isAbstractContract = $ancestor->isAbstract() && self::hasMethod($ancestor, $name);
            if ($isAbstractContract) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walk resolved in-file parents once, stopping at missing types or cycles.
     *
     * @param array<string, Class_|Interface_|Trait_> $byName
     *
     * @return iterable<Class_>
     */
    private static function ancestors(Class_ $class, array $byName): iterable
    {
        $seen = [spl_object_id($class) => true];
        while ($class->extends instanceof Name) {
            $parent = $byName[$class->extends->getLast()] ?? null;
            $isUnvisitedParent = $parent instanceof Class_ && ! isset($seen[spl_object_id($parent)]);
            if (! $isUnvisitedParent) {
                return;
            }

            $seen[spl_object_id($parent)] = true;
            yield $parent;
            $class = $parent;
        }
    }

    /**
     * Match the declared spelling without changing the existing case-sensitive policy.
     */
    private static function hasMethod(Class_|Interface_ $type, string $name): bool
    {
        foreach ($type->getMethods() as $method) {
            $matches = $method->name->toString() === $name;
            if ($matches) {
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
            $isName = $impl instanceof Name;
            if (! $isName) {
                continue;
            }

            $iface = $byName[$impl->getLast()] ?? null;
            $isInterfaceContract = $iface instanceof Interface_ && self::hasMethod($iface, $name);
            if ($isInterfaceContract) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check the supplied input for php override attribute.
     */
    private static function hasPhpOverrideAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $n = $attr->name;
                $isOverride = $n instanceof Name && $n->getLast() === 'Override';
                if ($isOverride) {
                    return true;
                }
            }
        }

        return false;
    }
}
