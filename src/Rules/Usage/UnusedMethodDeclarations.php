<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\CallableIdentity;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Compact declarations belong to the usage rule and never retain AST or source text.
 *
 * @phpstan-import-type MethodCall from UnusedMethodCalls
 *
 * @phpstan-type Method array{name: string, line: int, visibility: string, candidate: bool, calls: list<MethodCall>}
 * @phpstan-type Declaration array{name: string, kind: string, abstract: bool, parent: string|null, interfaces: list<string>, methods: array<string, Method>, uses: list<array>}
 */
final class UnusedMethodDeclarations
{
    /**
     * Names have already been resolved by the shared AST collection stage.
     *
     * @param list<Node> $nodes
     *
     * @return list<Declaration>
     */
    public static function collect(array $nodes): array
    {
        $types = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Stmt\ClassLike::class) as $type) {
            $types[] = [
                'name'       => CallableIdentity::type($type),
                'kind'       => $type instanceof Stmt\Trait_ ? 'trait' : 'class',
                'abstract'   => $type instanceof Stmt\Interface_ || ($type instanceof Stmt\Class_ && $type->isAbstract()),
                'parent'     => $type instanceof Stmt\Class_ ? $type->extends?->toLowerString() : null,
                'interfaces' => self::interfaces($type),
                'methods'    => self::methods($type),
                'uses'       => self::uses($type),
            ];
        }

        return $types;
    }

    /**
     * Interfaces also expose their parents for inherited contract checks.
     *
     * @return list<string>
     */
    private static function interfaces(Stmt\ClassLike $type): array
    {
        $names = match (true) {
            $type instanceof Stmt\Interface_                          => $type->extends,
            $type instanceof Stmt\Class_, $type instanceof Stmt\Enum_ => $type->implements,
            default                                                   => [],
        };

        return array_map(static fn (Node\Name $name): string => $name->toLowerString(), $names);
    }

    /**
     * Preserve existing candidate categories; project contracts are resolved after collection.
     *
     * @return array<string, Method>
     */
    private static function methods(Stmt\ClassLike $type): array
    {
        $methods = [];
        foreach ($type->getMethods() as $method) {
            $eligibleType = $type instanceof Stmt\Class_ || $type instanceof Stmt\Trait_;
            $methods[strtolower($method->name->toString())] = [
                'name'       => $method->name->toString(), 'line' => $method->getStartLine(),
                'visibility' => self::visibility($method->flags),
                'candidate'  => $eligibleType && self::isCandidate($method, $type),
                'calls'      => UnusedMethodCalls::collect($method->stmts ?? [], ! $method->isStatic(), UnusedMethodCalls::parameters($method->params)),
            ];
        }

        return $methods;
    }

    /**
     * Preserve private/protected categories, magic exclusions, and abstract lifecycle hooks.
     */
    private static function isCandidate(Stmt\ClassMethod $method, Stmt\Class_|Stmt\Trait_ $type): bool
    {
        $excluded = $method->isAbstract() || $method->isPublic() || self::isMagic($method);
        if ($excluded) {
            return false;
        }

        if ($method->isPrivate()) {
            return true;
        }

        if (! $type instanceof Stmt\Class_) {
            return false;
        }

        $abstractHook = $method->isFinal() && $type->isAbstract();

        return ! $abstractHook && ! self::hasOverride($method);
    }

    /**
     * Only PHP's existing magic names are excluded, compared without case sensitivity.
     */
    private static function isMagic(Stmt\ClassMethod $method): bool
    {
        return in_array($method->name->toLowerString(), [
            '__construct', '__destruct', '__call', '__callstatic', '__get', '__set', '__isset', '__unset',
            '__sleep', '__wakeup', '__serialize', '__unserialize', '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo',
        ], true);
    }

    /**
     * Explicit override metadata preserves the existing external-contract exclusion.
     */
    private static function hasOverride(Stmt\ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $name = strtolower($attribute->name->getLast());
                if ($name === 'override') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Keep aliases and precedence choices attached to their original use group.
     *
     * @return list<array>
     */
    private static function uses(Stmt\ClassLike $type): array
    {
        $uses = [];
        foreach ($type->getTraitUses() as $use) {
            $uses[] = [
                'traits'      => array_map(static fn (Node\Name $name): string => $name->toLowerString(), $use->traits),
                'adaptations' => array_map(self::adaptation(...), $use->adaptations),
            ];
        }

        return $uses;
    }

    /**
     * Decode only PHP visibility; aliases may retain the original modifier.
     */
    public static function visibility(int $flags): string
    {
        return match (true) {
            ($flags & Stmt\Class_::MODIFIER_PRIVATE) !== 0   => 'private',
            ($flags & Stmt\Class_::MODIFIER_PROTECTED) !== 0 => 'protected',
            default                                          => 'public',
        };
    }

    /**
     * Preserve both excluded implementations and aliases for later resolution.
     */
    private static function adaptation(Stmt\TraitUseAdaptation $adaptation): array
    {
        $base = ['trait' => $adaptation->trait?->toLowerString(), 'method' => strtolower($adaptation->method->toString())];
        if ($adaptation instanceof Stmt\TraitUseAdaptation\Precedence) {
            return $base + ['kind' => 'precedence', 'instead' => array_map(static fn (Node\Name $name): string => $name->toLowerString(), $adaptation->insteadof)];
        }

        assert($adaptation instanceof Stmt\TraitUseAdaptation\Alias);

        $visibility = ($adaptation->newModifier ?? 0) & Modifiers::VISIBILITY_MASK;

        return $base + ['kind' => 'alias', 'alias' => $adaptation->newName?->toLowerString(), 'visibility' => $visibility === 0 ? null : self::visibility($visibility)];
    }
}
