<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use function array_pop;
use function count;
use function explode;
use function implode;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * @phpstan-import-type ClassFacts from FileFacts
 */
final class ClassFactsExtractor
{
    /**
     * @return ClassFacts
     */
    public static function extract(Class_|Trait_ $node, ?LogicalLineMap $logicalLineMap = null): array
    {
        [$namespace, $depth] = self::extractNamespace($node);

        return [
            'kind'                => $node instanceof Trait_ ? 'trait' : 'class',
            'line'                => $node->getStartLine(),
            'loc'                 => $logicalLineMap?->countBetween($node->getStartLine(), $node->getEndLine())
                ?? $node->getEndLine() - $node->getStartLine() + 1,
            'methods'             => 0,
            'properties'          => self::countProperties($node),
            'public_methods'      => self::countPublicMethods($node),
            'dependencies'        => self::countDependencies($node),
            'efferent_coupling'   => 0,
            'traits'              => self::countTraits($node),
            'interfaces'          => $node instanceof Class_ ? count($node->implements) : 0,
            'namespace'           => $namespace,
            'namespace_depth'     => $depth,
        ];
    }

    /**
     * Count properties in the supplied syntax.
     */
    private static function countProperties(Class_|Trait_ $node): int
    {
        return count(PropertyMetrics::keys($node));
    }

    /**
     * Return the declared parent class when one is present.
     */
    public static function parentType(Class_ $node): ?string
    {
        $isName = $node->extends instanceof Name;
        if (! $isName) {
            return null;
        }

        $resolved = $node->extends->getAttribute('resolvedName');

        return $resolved instanceof Name ? $resolved->toString() : $node->extends->toString();
    }

    /**
     * @return list<string>
     */
    public static function implementedInterfaceNames(Class_ $node): array
    {
        return self::resolvedNames($node->implements);
    }

    /**
     * @return list<string>
     */
    public static function extendedInterfaceNames(Interface_ $node): array
    {
        return self::resolvedNames($node->extends);
    }

    /**
     * @param list<Name> $names
     *
     * @return list<string>
     */
    private static function resolvedNames(array $names): array
    {
        return array_map(static function (Name $name): string {
            $resolved = $name->getAttribute('resolvedName');

            return $resolved instanceof Name ? $resolved->toString() : $name->toString();
        }, $names);
    }

    /**
     * Count public methods in the supplied syntax.
     */
    private static function countPublicMethods(Class_|Trait_ $node): int
    {
        $count = 0;
        foreach ($node->getMethods() as $m) {
            if ($m->isPublic()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public static function publicMethodNames(Class_|Trait_ $node): array
    {
        $names = [];

        foreach ($node->getMethods() as $method) {
            if ($method->isPublic()) {
                $names[] = $method->name->toString();
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function usedTraitNames(Class_|Trait_ $node): array
    {
        $names = [];

        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $resolved = $trait->getAttribute('resolvedName');
                if ($resolved instanceof Name) {
                    $names[] = $resolved->toString();

                    continue;
                }

                $traitName = $trait->toString();
                [$namespace] = self::extractNamespace($node);
                $isQualifiedTrait = $namespace === '' || $trait->isFullyQualified();
                $names[] = $isQualifiedTrait
                    ? $traitName
                    : $namespace.'\\'.$traitName;
            }
        }

        return $names;
    }

    /**
     * Count dependencies in the supplied syntax.
     */
    private static function countDependencies(Class_|Trait_ $node): int
    {
        foreach ($node->getMethods() as $m) {
            $isConstructor = $m->name->toLowerString() === '__construct';
            if ($isConstructor) {
                return count($m->params);
            }
        }

        return 0;
    }

    /**
     * Count traits in the supplied syntax.
     */
    private static function countTraits(Class_|Trait_ $node): int
    {
        $count = 0;
        foreach ($node->getTraitUses() as $traitUse) {
            $count += count($traitUse->traits);
        }

        return $count;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function extractNamespace(Class_|Trait_ $node): array
    {
        $isPresent = isset($node->namespacedName);
        if (! $isPresent) {
            return ['', 0];
        }

        $full = $node->namespacedName->toString();
        $parts = explode('\\', $full);
        $hasNoNamespace = count($parts) <= 1;
        if ($hasNoNamespace) {
            return ['', 0];
        }

        array_pop($parts);

        return [implode('\\', $parts), count($parts)];
    }
}
