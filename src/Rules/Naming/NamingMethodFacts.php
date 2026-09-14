<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function collect;

use Cosmira\Soda\Analysis\FileFacts;

use function ltrim;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

use function strtolower;

/**
 * @internal
 *
 * @phpstan-import-type NamingMethod from FileFacts
 */
final class NamingMethodFacts
{
    /**
     * @return NamingMethod
     */
    public static function fromClassMethod(ClassMethod $node, ?string $class): array
    {
        $methodName = $node->name->toString();
        $fullName = $class !== null ? $class.'::'.$methodName : $methodName;

        return [
            'name'                 => $fullName,
            'methodName'           => $methodName,
            'class'                => $class,
            'firstParamType'       => self::firstParamType($node->params),
            'returnType'           => self::returnTypeLabel($node->getReturnType()),
            'line'                 => $node->getStartLine(),
            'isPublic'             => $node->isPublic(),
            'hasOverrideAttribute' => self::hasOverrideAttribute($node->attrGroups),
        ];
    }

    /**
     * @return NamingMethod|null
     */
    public static function fromFunction(Function_ $node): ?array
    {
        $name = $node->namespacedName?->toString();
        $hasNoName = $name === null || $name === '';

        if ($hasNoName) {
            return null;
        }

        return [
            'name'                 => $name,
            'methodName'           => $name,
            'class'                => null,
            'firstParamType'       => self::firstParamType($node->params),
            'returnType'           => self::returnTypeLabel($node->getReturnType()),
            'line'                 => $node->getStartLine(),
            'isPublic'             => true,
            'hasOverrideAttribute' => false,
        ];
    }

    /**
     * @param list<AttributeGroup> $attributeGroups
     */
    private static function hasOverrideAttribute(array $attributeGroups): bool
    {
        foreach ($attributeGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $isOverride = strtolower(ltrim($attribute->name->toString(), '\\')) === 'override';
                if ($isOverride) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<Node\Param> $params
     */
    private static function firstParamType(array $params): ?string
    {
        $first = collect($params)->first();
        $lacksType = $first === null || $first->type === null;

        if ($lacksType) {
            return null;
        }

        return PhpTypeName::fromTypeNode($first->type);
    }

    /**
     * Describe a declared return type for redundancy checks.
     */
    private static function returnTypeLabel(Node\Identifier|Node\Name|Node\ComplexType|null $returnType): ?string
    {
        return $returnType !== null ? PhpTypeName::fromTypeNode($returnType) : null;
    }
}
