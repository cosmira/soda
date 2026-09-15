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
                    'reads'               => array_keys(array_filter($method->params, static fn (Node\Param $input): bool => (new ParameterUseAnalysis($input))->isUsed($method))),
                ];
            }

            $name = strtolower($class->namespacedName?->toString() ?? $class->name->toString());
            $parentNames = array_map(static fn (Name $parent): string => $parent->toString(), $parents);
            $types[$name] = ['parentNames' => $parentNames, 'parents' => array_map(strtolower(...), $parentNames), 'methods' => $methods, 'factoryDispatch' => FactoryDispatchPatterns::collect($class)];
        }

        return $types;
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
            $parentType = $this->types[$parent] ?? [];
            $methods = $parentType['methods'] ?? [];
            $signature = $methods[$method] ?? [];
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
