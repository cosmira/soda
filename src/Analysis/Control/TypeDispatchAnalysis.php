<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Control;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Extracts the tested type/discriminator families of one lexical method.
 */
final class TypeDispatchAnalysis
{
    /**
     * Direct receiver aliases scoped to the method currently being scanned.
     */
    private array $aliases = [];

    /**
     * Keep separate receivers separate; collect each family once per method.
     */
    public function decisions(Stmt\ClassMethod $method): array
    {
        $this->aliases = [];
        $groups = [];
        $nodes = array_reverse($method->stmts ?? []);
        while ($nodes !== []) {
            $node = array_pop($nodes);
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            $this->record($node, $groups);
            foreach (array_reverse($node->getSubNodeNames()) as $field) {
                $child = get_object_vars($node)[$field];
                array_push($nodes, ...($child instanceof Node ? [$child] : (is_array($child) ? array_reverse($child) : [])));
            }
        }

        return $this->families($groups);
    }

    /**
     * Gather decisions and update straightforward receiver aliases in source order.
     */
    private function record(Node $node, array &$groups): void
    {
        if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->aliases[$node->var->name] = $this->receiver($node->expr) ?? '$'.$node->var->name.'@'.$node->getStartLine();
        }

        $isComparison = in_array($node->getType(), ['Expr_BinaryOp_Identical', 'Expr_BinaryOp_Equal'], true);
        if ($node instanceof Expr\BinaryOp && $isComparison) {
            $this->comparison($node, $groups);
        }

        if ($node instanceof Expr\Instanceof_) {
            $receiver = $this->receiver($node->expr);
            $this->add($groups, $receiver, DispatchedTypeValues::instanceClass($node));
        }

        if ($node instanceof Expr\Match_ || $node instanceof Stmt\Switch_) {
            $this->dispatch($node, $groups);
        }
    }

    /**
     * Dynamic receivers and calls do not establish a stable tested value.
     */
    private function receiver(Expr $expression): ?string
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $this->aliases[$expression->name] ?? '$'.$expression->name;
        }

        if ($expression instanceof Expr\PropertyFetch && $expression->name instanceof Node\Identifier) {
            $owner = $this->receiver($expression->var);

            return $owner === null ? null : $owner.'->'.$expression->name->toString();
        }

        return null;
    }

    /**
     * Equality-based discriminator branches contribute the same values as switch or match arms.
     */
    private function comparison(Expr\BinaryOp $comparison, array &$groups): void
    {
        foreach ([[$comparison->left, $comparison->right], [$comparison->right, $comparison->left]] as [$subject, $value]) {
            $receiver = $this->receiver($subject);
            $this->add($groups, $receiver, DispatchedTypeValues::discriminator($value));
        }
    }

    /**
     * Record each receiver/value pair once, independent of the spelling of its dispatch syntax.
     */
    private function add(array &$groups, ?string $receiver, ?string $value): void
    {
        if ($receiver === null || $value === null) {
            return;
        }

        $values = $groups[$receiver] ?? [];
        $values[$value] = true;
        $groups[$receiver] = $values;
    }

    /**
     * Normalize switch and match arms into class or scalar discriminator families.
     */
    private function dispatch(Node $node, array &$groups): void
    {
        [$selector, $arms] = (new LiteralDispatch)->of($node);
        $subject = DispatchedTypeValues::subject($selector);
        $receiver = $this->receiver($subject ?? $selector);
        foreach ($arms as [$condition]) {
            $value = DispatchedTypeValues::className($condition);
            if ($value === null && ! $subject instanceof Expr) {
                $value = DispatchedTypeValues::discriminator($condition);
            }

            $this->add($groups, $receiver, $value);
        }
    }

    /**
     * A single tested alternative is not a repeated type family.
     */
    private function families(array $groups): array
    {
        $sets = [];
        foreach ($groups as $types) {
            if (count($types) >= 2) {
                $names = array_keys($types);
                sort($names);
                $sets[] = $names;
            }
        }

        return $sets;
    }
}
