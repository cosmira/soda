<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\State;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Collects lexical private-state reads with conservative receiver flow across branches.
 */
final class PrivateStateReads
{
    /**
     * Start a lexical scope with its own receiver map; no syntax escapes this analysis.
     */
    public function collect(array $nodes, string $owner, array $receivers = ['this' => true]): array
    {
        $reads = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Stmt\ClassLike || $node instanceof Stmt\Function_) {
                continue;
            }

            $reads += $this->node($node, $owner, $receivers);
        }

        return $reads;
    }

    /**
     * Assignments evaluate the old receiver on their right before changing the target identity.
     */
    private function node(Node $node, string $owner, array &$receivers): array
    {
        if ($node instanceof Node\FunctionLike) {
            $scope = StateReceivers::callableReceivers($node, $owner, $receivers);

            return $this->collect($node->getStmts() ?? [], $owner, $scope);
        }

        if ($node instanceof Stmt\Expression) {
            return $this->node($node->expr, $owner, $receivers);
        }

        if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $isReceiver = StateReceivers::isReceiver($node->expr, $owner, $receivers);
            $reads = $this->node($node->expr, $owner, $receivers);
            $receivers[$node->var->name] = $isReceiver;

            return $reads;
        }

        return $this->children($node, $owner, $receivers);
    }

    /**
     * Collect the current access and join receiver effects from conditional child paths.
     */
    private function children(Node $node, string $owner, array &$receivers): array
    {
        $member = PrivateStateAccess::member($node, $owner, $receivers);
        $reads = $member === null ? [] : [$member => true];
        foreach ($node->getSubNodeNames() as $field) {
            $child = get_object_vars($node)[$field];
            $children = $child instanceof Node ? [$child] : (is_array($child) ? $child : []);
            $effects = $this->branch($children, $owner, $receivers);
            $reads += $effects['reads'];
            foreach ($effects['receivers'] as $name => $isReceiver) {
                $receivers[$name] = ($receivers[$name] ?? false) || $isReceiver;
            }
        }

        return $reads;
    }

    /**
     * A branch may establish a same-class alias; retain that possible receiver for later reads.
     */
    private function branch(array $nodes, string $owner, array $receivers): array
    {
        $reads = [];
        foreach ($nodes as $node) {
            if (! $node instanceof Node || $node instanceof Stmt\ClassLike || $node instanceof Stmt\Function_) {
                continue;
            }

            $reads += $this->node($node, $owner, $receivers);
        }

        return ['reads' => $reads, 'receivers' => $receivers];
    }
}
