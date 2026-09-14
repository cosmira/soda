<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;

/** Collects type references from member signatures, class operands and catches. */
final readonly class EfferentCouplingMemberScanner
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(private EfferentCouplingReferences $types) {}

    /**
     * Enter using the current analysis state.
     */
    public function enter(Node $node): void
    {
        if ($node instanceof Property) {
            $this->types->recordType($node->type);
        }

        $isCallable = $node instanceof ClassMethod || $node instanceof Closure || $node instanceof ArrowFunction;
        if ($isCallable) {
            foreach ($node->params as $parameter) {
                $this->types->recordType($parameter->type);
            }

            $this->types->recordType($node->getReturnType());
        }

        $this->collectClassOperand($node);

        if ($node instanceof Catch_) {
            foreach ($node->types as $catchType) {
                $this->types->recordName($catchType);
            }
        }
    }

    /**
     * Collect class operand from the current syntax context.
     */
    private function collectClassOperand(Node $node): void
    {
        $hasClassOperand = $node instanceof New_ || $node instanceof StaticCall
            || $node instanceof StaticPropertyFetch || $node instanceof ClassConstFetch || $node instanceof Instanceof_;
        if ($hasClassOperand) {
            $this->types->recordClassOperand($node->class);
        }
    }
}
