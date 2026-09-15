<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\State;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Identifies reads of private slots separately from field and array-element writes.
 */
final class PrivateStateAccess
{
    /**
     * Return the referenced member key only when this node reads a known owner receiver.
     */
    public static function member(Node $node, string $owner, array $receivers): ?string
    {
        if ($node instanceof Expr\ClassConstFetch && StateReceivers::isOwnerType($node->class, $owner)) {
            $constant = $node->name instanceof Node\Identifier ? $node->name->toString() : '*';

            return 'constant:'.$constant;
        }

        $isProperty = $node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch || $node instanceof Expr\StaticPropertyFetch;
        if (! $isProperty || self::isWriteOnly($node)) {
            return null;
        }

        return self::property($node, $owner, $receivers);
    }

    /**
     * Resolve named or dynamic field reads on a proven instance or static owner.
     */
    private static function property(Expr\PropertyFetch|Expr\NullsafePropertyFetch|Expr\StaticPropertyFetch $node, string $owner, array $receivers): ?string
    {
        $isOwner = $node instanceof Expr\StaticPropertyFetch
            ? StateReceivers::isOwnerType($node->class, $owner)
            : StateReceivers::isReceiver($node->var, $owner, $receivers);
        $isNamed = $node->name instanceof Node\Identifier || $node->name instanceof Node\VarLikeIdentifier;
        $property = $isNamed ? $node->name->toString() : '*';

        return $isOwner ? 'property:'.$property : null;
    }

    /**
     * Writing an array element does not read the field's stored value; index expressions still do.
     */
    private static function isWriteOnly(Node $node): bool
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Expr\ArrayDimFetch && $parent->var === $node) {
            $node = $parent;
            $parent = $node->getAttribute('parent');
        }

        $isAssignment = $parent instanceof Expr\Assign || $parent instanceof Expr\AssignRef;

        return $isAssignment && $parent->var === $node;
    }
}
