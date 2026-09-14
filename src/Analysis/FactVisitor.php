<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Base for AST visitors that never modify the tree (always return null).
 *
 * @internal
 */
abstract class FactVisitor extends NodeVisitorAbstract
{
    /**
     * Visits a node before its children while keeping the AST unchanged.
     */
    #[\Override]
    public function enterNode(Node $node): array|int|Node|null
    {
        $this->doEnterNode($node);

        return null;
    }

    /**
     * Visits a node after its children while keeping the AST unchanged.
     */
    #[\Override]
    public function leaveNode(Node $node): array|int|Node|null
    {
        $this->doLeaveNode($node);

        return null;
    }

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    protected function doEnterNode(Node $node): void {}

    /**
     * Restore analysis state after traversing the children of this syntax node.
     */
    protected function doLeaveNode(Node $node): void {}
}
