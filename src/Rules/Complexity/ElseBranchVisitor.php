<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FactVisitor;
use PhpParser\Node;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;

/**
 * Collects explicit else and elseif branches during the shared AST traversal.
 *
 * @internal
 */
final class ElseBranchVisitor extends FactVisitor
{
    /**
     * @var list<array{line: int, kind: 'else'|'elseif'}>
     */
    private array $occurrences = [];

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        if ($node instanceof ElseIf_) {
            $this->occurrences[] = ['line' => $node->getStartLine(), 'kind' => 'elseif'];

            return;
        }

        if ($node instanceof Else_) {
            $this->occurrences[] = ['line' => $node->getStartLine(), 'kind' => 'else'];
        }
    }

    /**
     * @return list<array{line: int, kind: 'else'|'elseif'}>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }
}
