<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function implode;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;

/**
 * @internal
 */
final class PhpTypeName
{
    /**
     * Build the result from type node.
     */
    public static function fromTypeNode(Node $type): string
    {
        return match (true) {
            $type instanceof Name         => $type->toString(),
            $type instanceof Identifier   => $type->name,
            $type instanceof NullableType => self::fromTypeNode($type->type).'|null',
            $type instanceof UnionType    => self::unionLabel($type),
            default                       => '',
        };
    }

    /**
     * Combine the member names of a union return type.
     */
    private static function unionLabel(UnionType $type): string
    {
        return implode('|', array_map(
            self::fromTypeNode(...),
            $type->types,
        ));
    }
}
