<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
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
    /** @var array<int, ListOnlyArrayFinding> */
    private array $byLine = [];

    public function __construct(
        private readonly ListOnlyArrayStrictness $strictness,
    ) {}

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
     * @return list<ListOnlyArrayFinding>
     */
    public function findings(): array
    {
        ksort($this->byLine, SORT_NUMERIC);

        return array_values($this->byLine);
    }

    private function captureArrayDimFetch(ArrayDimFetch $fetch): void
    {
        $issue = null;
        if ($fetch->var instanceof ArrayDimFetch) {
            $issue = ListOnlyArrayIssue::NestedAccess;
        } elseif ($this->strictness === ListOnlyArrayStrictness::Strict && $this->hasStringLiteralKey($fetch)) {
            $issue = ListOnlyArrayIssue::StringKey;
        }

        if ($issue === null) {
            return;
        }

        $line = $fetch->getStartLine();
        $this->remember($line, $issue);
    }

    private function hasStringLiteralKey(ArrayDimFetch $fetch): bool
    {
        $dim = $fetch->dim;

        return $dim instanceof Expr && $dim instanceof String_;
    }

    private function captureDocBlockShape(Node $node): void
    {
        $doc = $node->getDocComment();
        if (! $doc instanceof Doc) {
            return;
        }

        if (! $this->docContainsArrayShape($doc->getText())) {
            return;
        }

        $this->remember($node->getStartLine(), ListOnlyArrayIssue::PhpDocShape);
    }

    private function remember(int $line, ListOnlyArrayIssue $issue): void
    {
        if ($line <= 0) {
            return;
        }

        if (isset($this->byLine[$line])) {
            return;
        }

        $this->byLine[$line] = new ListOnlyArrayFinding($line, $issue);
    }

    private function docContainsArrayShape(string $text): bool
    {
        return preg_match('/array\s*\{/i', $text) === 1;
    }
}
