<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Efferent coupling (Ce): distinct external types referenced by a named type.
 *
 * @internal
 */
final class EfferentCouplingVisitor extends FactVisitor
{
    /**
     * Dependency edges accumulated while the traversal owns a named type.
     */
    private readonly EfferentCouplingGraph $graph;

    /**
     * Resolves referenced names into fully qualified external type names.
     */
    private readonly EfferentCouplingReferences $types;

    /**
     * Opens and closes type frames so references attach to their owning declaration.
     */
    private readonly EfferentCouplingClassScanner $classScanner;

    /**
     * Records type references found inside member bodies and attributes.
     */
    private readonly EfferentCouplingMemberScanner $memberScanner;

    /**
     * Wire the graph, name resolution and both scanners into one traversal pipeline.
     */
    public function __construct()
    {
        $this->graph = new EfferentCouplingGraph;
        $this->types = new EfferentCouplingReferences($this->graph);
        $this->classScanner = new EfferentCouplingClassScanner($this->graph, $this->types);
        $this->memberScanner = new EfferentCouplingMemberScanner($this->types);
    }

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $isType = $node instanceof ClassLike;
        if ($isType) {
            $this->classScanner->enter($node);

            return;
        }

        $hasNoOwner = $this->graph->currentOwner() === null;

        if ($hasNoOwner) {
            return;
        }

        if (AnonymousClassBoundary::isInside($node)) {
            return;
        }

        if ($node instanceof Node\Attribute) {
            $this->types->recordName($node->name);
        }

        $this->memberScanner->enter($node);
    }

    /**
     * Restore analysis state after traversing the children of this syntax node.
     */
    #[\Override]
    protected function doLeaveNode(Node $node): void
    {
        $this->classScanner->leave($node);
    }

    /**
     * Return the number of distinct outgoing dependencies for each class.
     *
     * @psalm-return array<string, int>
     */
    public function couplingCountsByClass(): array
    {
        return $this->graph->couplingCountsByClass();
    }

    /**
     * Return distinct outgoing dependency names grouped by owning class.
     *
     * @return array<string, list<string>>
     */
    public function dependenciesByClass(): array
    {
        return $this->graph->dependenciesByClass();
    }
}
