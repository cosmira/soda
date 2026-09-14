<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Class_;

/**
 * @internal
 */
final class AnonymousClassBoundary
{
    /**
     * Determine whether direct member applies to the supplied input.
     */
    public static function isDirectMember(Node $node): bool
    {
        return self::isAnonymousClass($node->getAttribute('parent'));
    }

    /**
     * Detects nodes nested anywhere inside an anonymous class body.
     */
    public static function isInside(Node $node): bool
    {
        $current = $node->getAttribute('parent');

        while ($current instanceof Node) {
            if (self::isAnonymousClass($current)) {
                return true;
            }

            $current = $current->getAttribute('parent');
        }

        return false;
    }

    /**
     * Detects class nodes created by a new expression.
     */
    public static function isAnonymousClass(mixed $node): bool
    {
        return $node instanceof Class_
            && $node->getAttribute('parent') instanceof New_;
    }
}
