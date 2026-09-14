<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;

/**
 * Detects **private** and **protected** methods that are never called within their class, trait,
 * or any subclass / trait-user in the same file.
 *
 * Protected methods are skipped when: the same name is declared on an **abstract** ancestor
 * class or on an **implemented interface** in this file; the method has **#[\Override]**
 * (parent may live in another file); or the method is **final protected** on an **abstract**
 * class (shared API for subclasses).
 *
 * Calls tracked:
 *   - $this->method()  / self::method()  / static::method()
 *   - call_user_func([$this, 'method'])
 *   - dynamic: $this->$var() → treats ALL methods as potentially used
 */
final readonly class UnusedMethodAnalyser
{
    /**
     * Stores finder for this analysis instance.
     */
    private NodeFinder $finder;

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct()
    {
        $this->finder = new NodeFinder;
    }

    /**
     * @param Node[] $nodes Top-level AST nodes from a parsed file.
     *
     * @return list<array{class: string, method: string, visibility: string, line: int}>
     */
    public function analyse(array $nodes): array
    {
        $byName = $this->collectDeclarationsByName($nodes);

        if ($byName === []) {
            return [];
        }

        $fileIndex = [
            'usage'      => array_map(UnusedMethodCalls::collect(...), $byName),
            'children'   => $this->buildChildrenMap($byName),
            'traitUsers' => $this->buildTraitUsersMap($byName),
            'byName'     => $byName,
        ];

        $violations = [];

        foreach ($byName as $name => $type) {
            $isUnsupportedType = ! $type instanceof Class_ && ! $type instanceof Trait_;
            if ($isUnsupportedType) {
                continue;
            }

            $violations = array_merge($violations, $this->analyseType($name, $type, $fileIndex));
        }

        return $violations;
    }

    /**
     * @return array<string, Class_|Trait_|Interface_>
     */
    private function collectDeclarationsByName(array $nodes): array
    {
        $byName = [];

        foreach ($this->finder->find(
            $nodes,
            fn (Node $n): bool => $n instanceof Class_ || $n instanceof Trait_ || $n instanceof Interface_,
        ) as $type) {
            /** @var Class_|Trait_|Interface_ $type */
            $short = $type->name?->toString();
            if ($short !== null) {
                $byName[$short] = $type;
            }
        }

        return $byName;
    }

    /**
     * @param array<string, Class_|Trait_|Interface_> $byName
     */
    private function buildChildrenMap(array $byName): array
    {
        $children = [];

        foreach ($byName as $name => $type) {
            $hasParent = $type instanceof Class_ && $type->extends instanceof Name;
            if ($hasParent) {
                $parent = $type->extends->getLast();
                $list = $children[$parent] ?? [];
                $list[] = $name;
                $children[$parent] = $list;
            }
        }

        return $children;
    }

    /**
     * @param array<string, Class_|Trait_|Interface_> $byName
     */
    private function buildTraitUsersMap(array $byName): array
    {
        $users = [];

        foreach ($byName as $className => $type) {
            foreach ($this->traitNamesUsed($type) as $traitName) {
                $list = $users[$traitName] ?? [];
                $list[] = $className;
                $users[$traitName] = $list;
            }
        }

        return $users;
    }

    /**
     * @param array{
     *     usage: array<string, array{called: array<string, true>, hasDynamic: bool}>,
     *     children: array<string, list<string>>,
     *     traitUsers: array<string, list<string>>,
     *     byName: array<string, Class_|Trait_|Interface_>
     * } $fileIndex
     *
     * @return list<array{class: string, method: string, visibility: string, line: int}>
     */
    private function analyseType(string $name, Class_|Trait_ $type, array $fileIndex): array
    {
        $usage = $fileIndex['usage'];
        $children = $fileIndex['children'];
        $traitUsers = $fileIndex['traitUsers'];
        $byName = $fileIndex['byName'];
        $isUnusedTrait = $type instanceof Trait_ && ($traitUsers[$name] ?? []) === [];

        if ($isUnusedTrait) {
            return [];
        }

        $callers = [$name, ...($children[$name] ?? []), ...($traitUsers[$name] ?? [])];
        $called = $this->mergeCalledNames($usage, $callers);

        return $this->hasAnyDynamicCall($usage, $callers) ? [] : array_values(array_filter(array_map(
            fn (ClassMethod $m): ?array => $this->toViolation($name, $m, $called),
            $this->candidateMethods($type, $byName),
        ), fn (?array $x): bool => $x !== null));
    }

    /**
     * @return string[]
     */
    private function traitNamesUsed(Class_|Trait_|Interface_ $type): array
    {
        $names = [];
        foreach ($this->finder->findInstanceOf([$type], TraitUse::class) as $use) {
            foreach ($use->traits as $trait) {
                $names[] = $trait->getLast();
            }
        }

        return $names;
    }

    /**
     * @param array<string, array{called: array<string, true>, hasDynamic: bool}> $usage
     *
     * @return array<string, true>
     */
    private function mergeCalledNames(array $usage, array $callers): array
    {
        return array_reduce(
            $callers,
            static function (array $carry, string $c) use ($usage): array {
                $row = $usage[$c] ?? [];

                return $carry + ($row['called'] ?? []);
            },
            [],
        );
    }

    /**
     * @param array<string, array{called: array<string, true>, hasDynamic: bool}> $usage
     */
    private function hasAnyDynamicCall(array $usage, array $callers): bool
    {
        return array_filter($callers, static function (string $c) use ($usage): bool {
            $row = $usage[$c] ?? [];

            return $row['hasDynamic'] ?? false;
        }) !== [];
    }

    /**
     * @param array<string, true> $called
     */
    private function toViolation(string $class, ClassMethod $method, array $called): ?array
    {
        $isCalled = isset($called[$method->name->toString()]);

        return $isCalled ? null : [
            'class'      => $class,
            'method'     => $method->name->toString(),
            'visibility' => $method->isPrivate() ? 'private' : 'protected',
            'line'       => $method->getStartLine(),
        ];
    }

    /**
     * @param array<string, Class_|Trait_|Interface_> $byName
     *
     * @return list<ClassMethod>
     */
    private function candidateMethods(Class_|Trait_ $type, array $byName): array
    {
        return array_values(array_filter(
            $type->stmts ?? [],
            fn (Node $s): bool => $s instanceof ClassMethod && UnusedMethodCandidatePolicy::isCandidate($s, $type, $byName),
        ));
    }
}
