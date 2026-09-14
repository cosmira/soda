<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Names research scopes without adding anonymous declarations to legacy metrics.
 */
final class CallableIdentity
{
    /**
     * Use resolved names for declarations and source identities for anonymous scopes.
     */
    public static function name(Node $node): string
    {
        if ($node instanceof Stmt\ClassMethod) {
            $owner = $node->getAttribute('parent');

            return self::type($owner).'::'.$node->name->toString();
        }

        if ($node instanceof Stmt\Function_) {
            return $node->namespacedName?->toString() ?? $node->name->toString();
        }

        return sprintf('{callable}@%d:%d', $node->getStartLine(), $node->getStartFilePos());
    }

    /**
     * Keep anonymous class identities unique within one source file.
     */
    public static function type(Stmt\ClassLike $node): string
    {
        return $node->namespacedName?->toString()
            ?? sprintf('{anonymous}@%d:%d', $node->getStartLine(), $node->getStartFilePos());
    }
}
