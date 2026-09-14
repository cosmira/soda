<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;

/** Tracks callable ownership, including suppressed anonymous scopes. */
trait CallableScopes
{
    /**
     * @var list<string|null>
     */
    private array $methodScopes = [];

    /**
     * Save the current callable owner and enter the next named or suppressed scope.
     */
    protected function enterMethodScope(ClassMethod|Function_ $node): ?string
    {
        $name = $this->resolveMethodName($node);
        $this->methodScopes[] = $name;

        return $name;
    }

    /**
     * Restore the enclosing callable owner after leaving its nested scope.
     */
    protected function leaveMethodScope(): void
    {
        array_pop($this->methodScopes);
    }

    /**
     * Suspend named-callable accounting while traversing an anonymous scope.
     */
    protected function enterClosure(): void
    {
        $this->methodScopes[] = null;
    }

    /**
     * Resume the enclosing scope after an anonymous callable.
     */
    protected function leaveClosure(): void
    {
        $this->leaveMethodScope();
    }

    /**
     * Return the qualified active method, or null while its accounting is suspended.
     */
    protected function currentMethod(): ?string
    {
        return $this->methodScopes === [] ? null : $this->methodScopes[array_key_last($this->methodScopes)];
    }

    /**
     * Resolve the qualified callable name, excluding abstract and anonymous class members.
     */
    protected function resolveMethodName(ClassMethod|Function_ $node): ?string
    {
        if ($node instanceof Function_) {
            return $node->namespacedName?->toString();
        }

        $isExcluded = $node->isAbstract() || AnonymousClassBoundary::isDirectMember($node);
        if ($isExcluded) {
            return null;
        }

        $owner = $node->getAttribute('parent');
        if ($owner instanceof Interface_) {
            return null;
        }

        $class = $owner instanceof ClassLike ? $owner->namespacedName?->toString() : null;

        return $class === null ? null : $class.'::'.$node->name->toString();
    }
}
