<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Analysis\Documentation\PhpDocTypeReferences;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Collects interface contracts and consumer edges without retaining ASTs.
 */
final class InterfaceFacts
{
    /**
     * Separate outside consumers from references that become useful with their interface owner.
     *
     * @param list<Node> $nodes
     */
    public static function collect(array $nodes): array
    {
        $finder = new NodeFinder;
        $interfaces = [];
        foreach ($finder->findInstanceOf($nodes, Stmt\Interface_::class) as $interface) {
            $name = $interface->namespacedName?->toString() ?? $interface->name->toString();
            $interfaces[strtolower($name)] = [
                'name'    => $name,
                'line'    => $interface->getStartLine(),
                'methods' => array_map(fn (Stmt\ClassMethod $method): string => strtolower($method->name->toString()), $interface->getMethods()),
                'parents' => array_map(fn (Node\Name $parent): string => strtolower($parent->toString()), $interface->extends),
            ];
        }

        $references = [];
        $dependencies = [];
        foreach ($finder->findInstanceOf($nodes, Node\Name::class) as $reference) {
            if (! self::isTypeReference($reference)) {
                continue;
            }

            $target = strtolower($reference->toString());
            $owner = self::interfaceOwner($reference);
            if ($owner === null) {
                $references[$target] = true;

                continue;
            }

            $outgoing = $dependencies[$owner] ?? [];
            $outgoing[$target] = true;
            $dependencies[$owner] = $outgoing;
        }

        foreach ($finder->find($nodes, static fn (Node $node): bool => $node->getDocComment() instanceof Doc) as $node) {
            $documented = PhpDocTypeReferences::collect($node);
            $owner = self::interfaceOwner($node);
            if ($owner === null) {
                $references += $documented;

                continue;
            }

            $dependencies[$owner] = ($dependencies[$owner] ?? []) + $documented;
        }

        return ['declarations' => $interfaces, 'references' => $references, 'dependencies' => $dependencies];
    }

    /**
     * Imports, declarations of implementation, and function or constant names do not consume a type.
     */
    private static function isTypeReference(Node\Name $reference): bool
    {
        $parent = $reference->getAttribute('parent');
        if ($parent instanceof Stmt\Class_ && in_array($reference, $parent->implements, true)) {
            return false;
        }

        return ! in_array($parent?->getType(), [
            'Expr_FuncCall', 'Expr_ConstFetch', 'UseItem', 'Stmt_Use', 'Stmt_GroupUse', 'Stmt_Namespace',
        ], true);
    }

    /**
     * Interface signatures and inheritance only consume contracts when the owning interface is used.
     */
    private static function interfaceOwner(Node $node): ?string
    {
        $parent = $node;
        while ($parent instanceof Node) {
            if ($parent instanceof Stmt\Interface_) {
                return strtolower($parent->namespacedName?->toString() ?? $parent->name->toString());
            }

            $parent = $parent->getAttribute('parent');
        }

        return null;
    }
}
