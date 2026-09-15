<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Composition;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Recognizes bounded method-name dispatch on the current object.
 */
final readonly class FactoryDispatchPatterns
{
    /**
     * Keep patterns only when a method-name assignment is actually called on this object.
     */
    public static function collect(Stmt\ClassLike $class): array
    {
        $patterns = [];
        foreach ($class->getMethods() as $method) {
            $finder = new NodeFinder;
            $nodes = $method->stmts ?? [];
            $assignments = $finder->findInstanceOf($nodes, Expr\Assign::class);
            foreach ($finder->findInstanceOf($nodes, Expr\MethodCall::class) as $call) {
                if (! self::isOwnDispatch($call, $method)) {
                    continue;
                }

                $matches = array_filter($assignments, static fn (Expr\Assign $assignment): bool => $assignment->var instanceof Expr\Variable
                    && $assignment->var->name === $call->name->name && self::isOwnedBy($assignment, $method));
                if (count($matches) !== 1) {
                    continue;
                }

                $assignment = array_shift($matches);
                if ($assignment->getStartFilePos() >= $call->getStartFilePos()) {
                    continue;
                }

                $pattern = self::pattern($assignment->expr);
                $hasLiteral = str_replace('.*', '', $pattern) !== '';
                if ($hasLiteral) {
                    $patterns[] = '/^'.$pattern.'$/iD';
                }
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Distinguish this object's dispatch from calls on another collaborator.
     */
    private static function isOwnDispatch(Expr\MethodCall $call, Stmt\ClassMethod $method): bool
    {
        $isThis = $call->var instanceof Expr\Variable && $call->var->name === 'this';
        $isNamedVariable = $call->name instanceof Expr\Variable && is_string($call->name->name);

        return $isThis && $isNamedVariable && self::isOwnedBy($call, $method);
    }

    /**
     * Nested closures and classes cannot donate an assignment to an unrelated callable.
     */
    private static function isOwnedBy(Node $node, Stmt\ClassMethod $method): bool
    {
        $owner = $node->getAttribute('parent');
        while ($owner instanceof Node && ! $owner instanceof Node\FunctionLike) {
            $owner = $owner->getAttribute('parent');
        }

        return $owner === $method;
    }

    /**
     * Literal concatenation segments constrain the dynamically selected method name.
     */
    private static function pattern(Expr $expression): string
    {
        if ($expression instanceof String_) {
            return preg_quote($expression->value, '/');
        }

        if ($expression instanceof Expr\BinaryOp\Concat) {
            return self::pattern($expression->left).self::pattern($expression->right);
        }

        return '.*';
    }
}
