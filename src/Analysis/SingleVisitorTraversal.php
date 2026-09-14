<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Runs a single visitor over a small AST node list.
 */
final readonly class SingleVisitorTraversal
{
    /**
     * @param list<Node> $nodes
     */
    public static function traverse(array $nodes, NodeVisitor $visitor): void
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
    }
}
