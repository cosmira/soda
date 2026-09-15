<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Analysis\State\PrivateStateReads;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Records private declarations and reads in each lexical class or trait.
 */
final class PrivateStateFacts
{
    /**
     * @param list<Node> $nodes
     */
    public static function collect(array $nodes): array
    {
        $types = [];
        foreach ((new NodeFinder)->findInstanceOf($nodes, Stmt\ClassLike::class) as $type) {
            if ($type instanceof Stmt\Interface_ || $type->name === null) {
                continue;
            }

            $name = $type->namespacedName?->toString() ?? $type->name->toString();
            $declarations = self::declarations($type);
            $traits = [];
            foreach ($type->getTraitUses() as $use) {
                foreach ($use->traits as $trait) {
                    $traits[] = strtolower($trait->toString());
                }
            }

            $types[strtolower($name)] = [
                'name'         => $name, 'trait' => $type instanceof Stmt\Trait_,
                'declarations' => $declarations, 'traits' => $traits,
                'reads'        => (new PrivateStateReads)->collect(array_filter($type->stmts, fn (Node $node): bool => ! $node instanceof Stmt\ClassMethod), $name),
                'methodReads'  => self::methodReads($type, $name),
            ];
        }

        return $types;
    }

    /**
     * Preserve method provenance so overridden trait readers cannot protect unused state.
     */
    private static function methodReads(Stmt\ClassLike $type, string $owner): array
    {
        $reads = [];
        foreach ($type->getMethods() as $method) {
            $origin = strtolower($owner).'::'.$method->name->toLowerString();
            $reads[$origin] = (new PrivateStateReads)->collect([$method], $owner);
        }

        return $reads;
    }

    /**
     * Collect lexical private fields, promoted fields and constants independently of use sites.
     */
    private static function declarations(Stmt\ClassLike $type): array
    {
        $declarations = [];
        foreach ($type->getProperties() as $property) {
            if ($property->isPrivate()) {
                foreach ($property->props as $item) {
                    $declarations['property:'.$item->name->toString()] = $item->getStartLine();
                }
            }
        }

        $declarations += self::promotedFields($type);
        foreach ($type->getConstants() as $constant) {
            if ($constant->isPrivate()) {
                foreach ($constant->consts as $item) {
                    $declarations['constant:'.$item->name->toString()] = $item->getStartLine();
                }
            }
        }

        return $declarations;
    }

    /**
     * Constructor promotion declares private state even when its body is empty.
     */
    private static function promotedFields(Stmt\ClassLike $type): array
    {
        $declarations = [];
        foreach ($type->getMethod('__construct')?->params ?? [] as $parameter) {
            if ($parameter->isPrivate() && $parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                $declarations['property:'.$parameter->var->name] = $parameter->getStartLine();
            }
        }

        return $declarations;
    }
}
