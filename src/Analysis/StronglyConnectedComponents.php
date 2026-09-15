<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

/**
 * Tarjan's linear-time partition of a directed graph.
 */
final class StronglyConnectedComponents
{
    /**
     * Discovery order for each visited node.
     */
    private array $indices = [];

    /**
     * Earliest discovery reachable from the active depth-first subtree.
     */
    private array $low = [];

    /**
     * Nodes awaiting assignment to a component.
     */
    private array $stack = [];

    /**
     * Membership of the pending component stack.
     */
    private array $active = [];

    /**
     * Completed strongly connected components.
     */
    private array $components = [];

    /**
     * Next discovery index.
     */
    private int $next = 0;

    /**
     * @param array<string, list<string>> $graph
     */
    public function collect(array $graph): array
    {
        $this->indices = [];
        $this->low = [];
        $this->stack = [];
        $this->active = [];
        $this->components = [];
        $this->next = 0;
        foreach (array_keys($graph) as $node) {
            if (! isset($this->indices[$node])) {
                $this->visit($node, $graph);
            }
        }

        return $this->components;
    }

    /**
     * Visit outgoing edges, then remove a completed strongly connected component from the stack.
     */
    private function visit(string $node, array $graph): void
    {
        $this->indices[$node] = $this->low[$node] = $this->next++;
        $this->stack[] = $node;
        $this->active[$node] = true;
        foreach ($graph[$node] ?? [] as $target) {
            if (! isset($this->indices[$target])) {
                $this->visit($target, $graph);
                $this->low[$node] = min($this->low[$node], $this->low[$target]);

                continue;
            }

            if (isset($this->active[$target])) {
                $this->low[$node] = min($this->low[$node], $this->indices[$target]);
            }
        }

        if ($this->low[$node] !== $this->indices[$node]) {
            return;
        }

        $component = [];
        do {
            $member = array_pop($this->stack);
            unset($this->active[$member]);
            $component[] = $member;
        } while ($member !== $node);

        sort($component);
        $this->components[] = $component;
    }
}
