<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Recognizes syntactic direct recursion; mutual and dynamic recursion are outside v1.
 */
trait CognitiveRecursion
{
    /**
     * Add one recursion contribution per callable, rather than one per call site.
     */
    private function recordRecursion(Node $node): void
    {
        $isDirect = $this->isDirectCall($node);
        $shouldSkip = $this->recursive || ! $isDirect;
        if ($shouldSkip) {
            return;
        }

        $this->record($node, 1, 'syntactic direct recursion');
        $this->recursive = true;
    }

    /**
     * Resolve named functions and literal self/this calls without guessing dynamic targets.
     */
    private function isDirectCall(Node $node): bool
    {
        $isFunction = $node instanceof Expr\FuncCall && $node->name instanceof Node\Name;
        if ($isFunction) {
            $name = $node->name->getAttribute('namespacedName') ?? $node->name;

            return strtolower($name->toString()) === strtolower($this->name);
        }

        $calledMethod = $this->localMethodName($node);
        if ($calledMethod === null) {
            return false;
        }

        $separator = strrpos($this->name, '::');

        return $separator !== false && strtolower(substr($this->name, $separator + 2)) === $calledMethod;
    }

    /**
     * Accept literal this/self receivers and literal method names only.
     */
    private function localMethodName(Node $node): ?string
    {
        $isMethod = $node instanceof Expr\MethodCall && $node->var instanceof Expr\Variable && $node->var->name === 'this';
        $isStatic = $node instanceof Expr\StaticCall && $node->class instanceof Node\Name && strtolower($node->class->toString()) === 'self';

        $isKnown = ($isMethod || $isStatic) && $node->name instanceof Node\Identifier;

        return $isKnown ? $node->name->toLowerString() : null;
    }
}
