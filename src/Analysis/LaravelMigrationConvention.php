<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;

/** Recognizes Laravel's deliberately small, framework-prescribed migration boundary. */
final class LaravelMigrationConvention
{
    /**
     * Defines base class used by this policy.
     */
    private const string BASE_CLASS = 'illuminate\\database\\migrations\\migration';

    /**
     * Recognize the framework-prescribed Laravel migration contract.
     */
    public static function isMigration(Class_ $class): bool
    {
        $isDifferentBase = ! $class->extends instanceof Name || self::baseClassName($class->extends) !== self::BASE_CLASS;
        if ($isDifferentBase) {
            return false;
        }

        return self::hasPublicHook($class, 'up') && self::hasPublicHook($class, 'down');
    }

    /**
     * Resolve the normalized base-class name, including imported aliases.
     */
    private static function baseClassName(Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        return strtolower($resolved instanceof Name ? $resolved->toString() : $name->toString());
    }

    /**
     * Recognize a public lifecycle hook on a Laravel migration.
     */
    public static function isHook(ClassMethod $method): bool
    {
        $class = $method->getAttribute('parent');

        return $class instanceof Class_
            && self::isMigration($class)
            && in_array(strtolower($method->name->toString()), ['up', 'down'], true);
    }

    /**
     * Check that the migration declares the required public, parameterless hook.
     */
    private static function hasPublicHook(Class_ $class, string $name): bool
    {
        foreach ($class->getMethods() as $method) {
            $isPublicHook = strtolower($method->name->toString()) === $name
                && $method->isPublic()
                && $method->params === [];
            if ($isPublicHook) {
                return true;
            }
        }

        return false;
    }
}
