<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Efferent coupling (Ce): distinct external types referenced by a class or trait.
 *
 * @internal
 */
final class EfferentCouplingVisitor extends FactVisitor
{
    /**
     * Stores graph for this analysis instance.
     */
    private readonly EfferentCouplingGraph $graph;

    /**
     * Stores types for this analysis instance.
     */
    private readonly EfferentCouplingReferences $types;

    /**
     * Stores class scanner for this analysis instance.
     */
    private readonly EfferentCouplingClassScanner $classScanner;

    /**
     * Stores member scanner for this analysis instance.
     */
    private readonly EfferentCouplingMemberScanner $memberScanner;

    /**
     * Initialize the configured values and collaborators for this instance.
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
        $isType = $node instanceof Class_ || $node instanceof Trait_;
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
     * @psalm-return array<string, int>
     */
    public function couplingCountsByClass(): array
    {
        return $this->graph->couplingCountsByClass();
    }

    /**
     * @return array<string, list<string>>
     */
    public function dependenciesByClass(): array
    {
        return $this->graph->dependenciesByClass();
    }
}
