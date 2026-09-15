<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Analysis\Composition\FactoryDispatchPatterns;
use Cosmira\Soda\Rules\Usage\ParameterUseAnalysis;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Resolves mandatory inherited parameter positions across analysed source files.
 */
final readonly class InheritedParameterContracts
{
    /**
     * Store signatures and parent edges without retaining AST nodes.
     */
    public static function collect(array $nodes): array
    {
        $types = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Stmt\ClassLike::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            $parents = $class instanceof Stmt\Class_ ? [...($class->extends instanceof Name ? [$class->extends] : []), ...$class->implements] : [];
            if ($class instanceof Stmt\Interface_) {
                $parents = $class->extends;
            }

            $methods = [];
            foreach ($class->getMethods() as $method) {
                $methods[strtolower($method->name->toString())] = [
                    'arity'               => count($method->params), 'private' => $method->isPrivate(),
                    'constructorContract' => $class instanceof Stmt\Interface_ || $method->isAbstract(),
                    'forwardsParent'      => ForwardedParentConstructor::doesForward($method),
                    'reads'               => array_keys(array_filter($method->params, static fn (Node\Param $input): bool => (new ParameterUseAnalysis($input))->isUsed($method))),
                ];
            }

            $name = strtolower($class->namespacedName?->toString() ?? $class->name->toString());
            $parentNames = array_map(static fn (Name $parent): string => $parent->toString(), $parents);
            $traitNames = [];
            foreach ($class->getTraitUses() as $use) {
                foreach ($use->traits as $trait) {
                    $traitNames[] = $trait->toString();
                }
            }

            $types[$name] = [
                'parentClass'     => self::parentClass($class),
                'traitNames'      => $traitNames,
                'parentNames'     => $parentNames,
                'parents'         => array_map(strtolower(...), $parentNames),
                'methods'         => $methods,
                'factoryDispatch' => FactoryDispatchPatterns::collect($class),
            ];
        }

        return $types;
    }

    /**
     * Distinguish a concrete parent edge from implemented interface contracts.
     */
    private static function parentClass(Stmt\ClassLike $class): ?string
    {
        return $class instanceof Stmt\Class_ ? $class->extends?->toLowerString() : null;
    }

    /**
     * Index only declarations from this analysis, never autoload application code.
     */
    public function __construct(private array $types) {}

    /**
     * A declared ancestor signature protects only its own existing positions.
     */
    public function arity(?string $class, ?string $method): int
    {
        if ($class === null || $method === null) {
            return 0;
        }

        return $this->inherited(strtolower($class), strtolower($method), []);
    }

    /**
     * A transparent constructor adapter preserves only the actual parent's arity.
     */
    public function forwardedConstructorArity(?string $class): int
    {
        $declaration = $this->types[strtolower($class ?? '')] ?? [];
        $methods = $declaration['methods'] ?? [];
        $constructor = $methods['__construct'] ?? [];
        if (! ($constructor['forwardsParent'] ?? false)) {
            return 0;
        }

        $parent = $declaration['parentClass'] ?? null;
        $seen = [];
        while ($parent !== null && ! isset($seen[$parent])) {
            $seen[$parent] = true;
            $signature = $this->signature($parent, '__construct', []);
            if ($signature !== []) {
                return ($signature['private'] ?? true) ? 0 : $signature['arity'];
            }

            $declaration = $this->types[$parent] ?? [];
            $parent = $declaration['parentClass'] ?? null;
        }

        return 0;
    }

    /**
     * A base implementation may ignore input used by a polymorphic implementation.
     */
    public function isUsedByOverride(?string $class, ?string $method, int $position): bool
    {
        if ($class === null || $method === null || strtolower($method) === '__construct') {
            return false;
        }

        $class = strtolower($class);
        $method = strtolower($method);
        $ownType = $this->types[$class] ?? [];
        $ownMethods = $ownType['methods'] ?? [];
        $ownSignature = $ownMethods[$method] ?? [];
        if ($ownSignature['private'] ?? true) {
            return false;
        }

        foreach ($this->types as $name => $type) {
            $methods = $type['methods'] ?? [];
            $signature = $methods[$method] ?? [];
            $readsInput = ! ($signature['private'] ?? true) && in_array($position, $signature['reads'] ?? [], true);
            if ($readsInput && $this->hasAncestor($name, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Follow real inheritance edges rather than matching unrelated method names.
     */
    private function hasAncestor(string $type, string $ancestor): bool
    {
        $pending = [$type];
        $visited = [];
        while ($pending !== []) {
            $name = array_pop($pending);
            if (isset($visited[$name])) {
                continue;
            }

            $visited[$name] = true;
            $declaration = $this->types[$name] ?? [];
            $parents = $declaration['parents'] ?? [];
            if (in_array($ancestor, $parents, true)) {
                return true;
            }

            array_push($pending, ...$parents);
        }

        return false;
    }

    /**
     * Find an ancestor's effective declaration, including its imported trait methods.
     */
    private function signature(string $type, string $method, array $seen): array
    {
        if (isset($seen[$type])) {
            return [];
        }

        $seen[$type] = true;
        $declaration = $this->types[$type] ?? [];
        $methods = $declaration['methods'] ?? [];
        if (isset($methods[$method])) {
            return $methods[$method];
        }

        $matches = [];
        foreach ($declaration['traitNames'] ?? [] as $trait) {
            $signature = $this->signature(strtolower($trait), $method, $seen);
            if ($signature !== []) {
                $matches[] = $signature;
            }
        }

        return count($matches) === 1 ? reset($matches) : [];
    }

    /**
     * Traverse inherited contracts with cycle protection for incomplete source.
     */
    private function inherited(string $class, string $method, array $visited): int
    {
        if (isset($visited[$class])) {
            return 0;
        }

        $visited[$class] = true;
        $arity = 0;
        $declaration = $this->types[$class] ?? [];
        foreach ($declaration['parents'] ?? [] as $parent) {
            $signature = $this->signature($parent, $method, []);
            $isContract = ! ($signature['private'] ?? true);
            if ($method === '__construct') {
                $isContract = $isContract && ($signature['constructorContract'] ?? false);
            }

            if ($isContract) {
                $arity = max($arity, $signature['arity']);
            }

            $arity = max($arity, $this->inherited($parent, $method, $visited));
        }

        return $arity;
    }
}
