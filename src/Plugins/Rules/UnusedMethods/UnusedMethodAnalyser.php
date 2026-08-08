<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\UnusedMethods;

use function collect;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
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
 * class (shared API for subclasses, e.g. {@see SodaRule::exceeds()}).
 *
 * Calls tracked:
 *   - $this->method()  / self::method()  / static::method()
 *   - call_user_func([$this, 'method'])
 *   - dynamic: $this->$var() → treats ALL methods as potentially used
 */
final readonly class UnusedMethodAnalyser
{
    private NodeFinder $finder;

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
            'usage'      => array_map($this->collectUsage(...), $byName),
            'children'   => $this->buildChildrenMap($byName),
            'traitUsers' => $this->buildTraitUsersMap($byName),
            'byName'     => $byName,
        ];

        $violations = [];

        foreach ($byName as $name => $type) {
            if (! $type instanceof Class_ && ! $type instanceof Trait_) {
                continue;
            }

            $violations = array_merge($violations, $this->analyseType($name, $type, $fileIndex));
        }

        return $violations;
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

        if ($type instanceof Trait_ && ($traitUsers[$name] ?? []) === []) {
            return [];
        }

        $callers = [$name, ...($children[$name] ?? []), ...($traitUsers[$name] ?? [])];
        $called = $this->mergeCalledNames($usage, $callers);

        return $this->hasAnyDynamicCall($usage, $callers) ? [] : array_values(array_filter(array_map(
            fn (ClassMethod $m): ?array => $this->toViolation($name, $m, $called),
            $this->candidateMethods($type, $byName),
        ), fn (?array $x): bool => $x !== null));
    }

    /** @param array<string, true> $called */
    private function toViolation(string $class, ClassMethod $method, array $called): ?array
    {
        return isset($called[$method->name->toString()]) ? null : [
            'class'      => $class,
            'method'     => $method->name->toString(),
            'visibility' => $method->isPrivate() ? 'private' : 'protected',
            'line'       => $method->getStartLine(),
        ];
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

    /** @return array{called: array<string, true>, hasDynamic: bool} */
    private function collectUsage(Class_|Trait_|Interface_ $type): array
    {
        return [
            'called'     => array_merge(
                $this->methodCallNames($type),
                $this->staticCallNames($type),
                $this->callUserFuncNames($type),
            ),
            'hasDynamic' => $this->hasDynamicCall($type),
        ];
    }

    /** @return array<string, true> */
    private function methodCallNames(Class_|Trait_|Interface_ $type): array
    {
        return array_fill_keys(array_filter(array_map(
            function (Node $c): ?string {
                /** @var MethodCall $c */
                return is_a($c->name, 'PhpParser\Node\Identifier') ? $c->name->toString() : null;
            },
            $this->finder->find([$type], fn (Node $n): bool => is_a($n, 'PhpParser\Node\Expr\MethodCall')),
        ), fn (mixed $x): bool => $x !== null), true);
    }

    /** @return array<string, true> */
    private function staticCallNames(Class_|Trait_|Interface_ $type): array
    {
        return array_fill_keys(array_filter(array_map(
            function (Node $c): ?string {
                /** @var StaticCall $c */
                return (is_a($c->class, 'PhpParser\Node\Name')
                    && in_array((string) $c->class, ['self', 'static', 'parent'], true)
                    && is_a($c->name, 'PhpParser\Node\Identifier'))
                    ? $c->name->toString() : null;
            },
            $this->finder->find([$type], fn (Node $n): bool => is_a($n, 'PhpParser\Node\Expr\StaticCall')),
        ), fn (mixed $x): bool => $x !== null), true);
    }

    /** @return array<string, true> */
    private function callUserFuncNames(Class_|Trait_|Interface_ $type): array
    {
        return array_fill_keys(array_filter(array_map(
            $this->extractCallUserFuncName(...),
            $this->finder->find([$type], fn (Node $n): bool => $this->isCallUserFunc($n)),
        ), fn (mixed $x): bool => $x !== null), true);
    }

    private function isCallUserFunc(Node $n): bool
    {
        return is_a($n, 'PhpParser\Node\Expr\FuncCall')
            && is_a($n->name, 'PhpParser\Node\Name')
            && in_array((string) $n->name, ['call_user_func', 'call_user_func_array'], true);
    }

    private function extractCallUserFuncName(Node $call): ?string
    {
        /** @var FuncCall $call */
        $arg = collect($call->args)->first();

        if (! is_a($arg, 'PhpParser\Node\Arg') || ! is_a($arg->value, 'PhpParser\Node\Expr\Array_')) {
            return null;
        }

        $second = collect($arg->value->items)->get(1);

        return $second !== null && is_a($second->value, 'PhpParser\Node\Scalar\String_')
            ? $second->value->value
            : null;
    }

    private function hasDynamicCall(Class_|Trait_|Interface_ $type): bool
    {
        return $this->finder->find([$type], fn (Node $n): bool => is_a($n, 'PhpParser\Node\Expr\MethodCall')
            && is_a($n->name, 'PhpParser\Node\Expr\Variable'),
        ) !== [];
    }

    /** @param array<string, Class_|Trait_|Interface_> $byName */
    private function buildChildrenMap(array $byName): array
    {
        $children = [];

        foreach ($byName as $name => $type) {
            if ($type instanceof Class_ && $type->extends instanceof Name) {
                $parent = $type->extends->getLast();
                $list = $children[$parent] ?? [];
                $list[] = $name;
                $children[$parent] = $list;
            }
        }

        return $children;
    }

    /** @param array<string, Class_|Trait_|Interface_> $byName */
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

    /** @return string[] */
    private function traitNamesUsed(Class_|Trait_|Interface_ $type): array
    {
        $uses = $this->finder->find([$type], fn (Node $n): bool => is_a($n, 'PhpParser\Node\Stmt\TraitUse'));

        return array_merge([], ...array_map(
            function (Node $use): array {
                /** @var TraitUse $use */
                return array_map(fn (Name $t) => $t->getLast(), $use->traits);
            },
            $uses,
        ));
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

    /** @param array<string, array{called: array<string, true>, hasDynamic: bool}> $usage */
    private function hasAnyDynamicCall(array $usage, array $callers): bool
    {
        return array_filter($callers, static function (string $c) use ($usage): bool {
            $row = $usage[$c] ?? [];

            return $row['hasDynamic'] ?? false;
        }) !== [];
    }
}
