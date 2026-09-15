<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Control;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;

/**
 * Describes operations performed by a dispatch arm independently of its selected data.
 */
final class OperationSequence
{
    /**
     * Collect operation sequences in an arm; scalar data selection is allowed.
     */
    public function encode(array $body): ?string
    {
        $operations = [];
        foreach ($body as $node) {
            if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }

            $operation = $this->calledOperation($node);
            if ($operation !== null) {
                $operations[] = $operation;

                continue;
            }

            array_push($operations, ...$this->nested($node));
        }

        return $operations === [] ? null : implode('|', $operations);
    }

    /**
     * Keep receiver identity in method dispatch; identical names on different receivers differ.
     */
    private function calledOperation(Node $expression): ?string
    {
        if ($expression instanceof Expr\MethodCall && $expression->name instanceof Node\Identifier) {
            return 'method:'.(new Standard)->prettyPrintExpr($expression->var).'::'.strtolower($expression->name->toString());
        }

        if ($expression instanceof Expr\StaticCall && $expression->class instanceof Node\Name && $expression->name instanceof Node\Identifier) {
            return 'static:'.strtolower($expression->class->toString().'::'.$expression->name->toString());
        }

        if ($expression instanceof Expr\FuncCall && $expression->name instanceof Node\Name) {
            return 'function:'.strtolower($expression->name->toString());
        }

        $isNamedConstruction = $expression instanceof Expr\New_ && $expression->class instanceof Node\Name;

        return $isNamedConstruction ? 'new:'.strtolower($expression->class->toString()) : null;
    }

    /**
     * Traverse child expressions only when the current node is not itself an operation.
     */
    private function nested(Node $node): array
    {
        $operations = [];
        foreach ($node->getSubNodeNames() as $field) {
            $child = get_object_vars($node)[$field];
            $children = $child instanceof Node ? [$child] : (is_array($child) ? $child : []);
            $nested = $this->encode($children);
            if ($nested !== null) {
                $operations[] = $nested;
            }
        }

        return $operations;
    }
}
