<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @internal Whether a method is eligible for unused-method reporting.
 */
final class UnusedMethodCandidatePolicy
{
    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    public static function isCandidate(ClassMethod $method, Class_|Trait_ $type, array $byName): bool
    {
        if (self::isExcludedMember($method)) {
            return false;
        }

        $isMagicMethod = in_array($method->name->toString(), self::magic(), true);

        if ($isMagicMethod) {
            return false;
        }

        if ($method->isPrivate()) {
            return true;
        }

        return self::isReportableProtected($method, $type, $byName);
    }

    /**
     * @return list<string>
     */
    private static function magic(): array
    {
        return [
            '__construct', '__destruct', '__call', '__callStatic',
            '__get', '__set', '__isset', '__unset',
            '__sleep', '__wakeup', '__serialize', '__unserialize',
            '__toString', '__invoke', '__set_state', '__clone', '__debugInfo',
        ];
    }

    /**
     * Determine whether excluded member applies to the supplied input.
     */
    private static function isExcludedMember(ClassMethod $method): bool
    {
        if ($method->isAbstract()) {
            return true;
        }

        if ($method->isPublic()) {
            return true;
        }

        return ! $method->isPrivate() && ! $method->isProtected();
    }

    /**
     * @param array<string, Class_|Interface_|Trait_> $byName
     */
    private static function isReportableProtected(ClassMethod $method, Class_|Trait_ $type, array $byName): bool
    {
        $isNonCandidate = ! $method->isProtected() || ! $type instanceof Class_;
        if ($isNonCandidate) {
            return false;
        }

        $isAbstractHook = $method->isFinal() && $type->isAbstract();

        if ($isAbstractHook) {
            return false;
        }

        return ! UnusedMethodProtectedContract::isDeclaredOnSupertype($method, $type, $byName);
    }
}
