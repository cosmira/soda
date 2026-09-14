<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * One AST walk; checks depend on {@see ListOnlyArrayStrictness}.
 *
 * @internal
 */
final class ListOnlyArrayCollectingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, true>
     */
    private array $byLine = [];

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private readonly ListOnlyArrayStrictness $strictness,
    ) {}

    /**
     * Visit the syntax node and return the appropriate traversal instruction.
     */
    public function enterNode(Node $node): null
    {
        if ($node instanceof ArrayDimFetch) {
            $this->captureArrayDimFetch($node);
        }

        if ($this->strictness === ListOnlyArrayStrictness::Strict) {
            $this->captureDocBlockShape($node);
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function lines(): array
    {
        ksort($this->byLine, SORT_NUMERIC);

        return array_keys($this->byLine);
    }

    /**
     * Capture array dim fetch while visiting the syntax tree.
     */
    private function captureArrayDimFetch(ArrayDimFetch $fetch): void
    {
        $isNested = $fetch->var instanceof ArrayDimFetch;
        $isStrictKey = $this->strictness === ListOnlyArrayStrictness::Strict && $fetch->dim instanceof String_;
        if ($isNested || $isStrictKey) {
            $this->remember($fetch->getStartLine());
        }
    }

    /**
     * Capture doc block shape while visiting the syntax tree.
     */
    private function captureDocBlockShape(Node $node): void
    {
        $doc = $node->getDocComment();
        $isDoc = $doc instanceof Doc;
        if (! $isDoc) {
            return;
        }

        $hasArrayShape = $this->docContainsArrayShape($doc->getText());

        if (! $hasArrayShape) {
            return;
        }

        $this->remember($node->getStartLine());
    }

    /**
     * Check whether the documentation declares an associative array shape.
     */
    private function docContainsArrayShape(string $text): bool
    {
        return preg_match('/array\s*\{/i', $text) === 1;
    }

    /**
     * Remember using the current analysis state.
     */
    private function remember(int $line): void
    {
        if ($line <= 0) {
            return;
        }

        $this->byLine[$line] = true;
    }
}
