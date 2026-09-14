<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\ConditionExtractor;
use Cosmira\Soda\Analysis\FactVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/** Collects control-flow predicates that hide more than one concern. */
final class ComplexControlConditionVisitor extends FactVisitor
{
    /**
     * @var list<array{line: int, reason: 'multiple_operations'}>
     */
    private array $occurrences = [];

    /**
     * Stores finder for this analysis instance.
     */
    private readonly NodeFinder $finder;

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $condition = ConditionExtractor::extract($node);
        $isExpr = $condition instanceof Expr;
        if (! $isExpr) {
            return;
        }

        $reason = $this->reason($condition);
        if ($reason === null) {
            return;
        }

        $this->occurrences[] = ['line' => $condition->getStartLine(), 'reason' => $reason];
    }

    /**
     * @return list<array{line: int, reason: 'multiple_operations'}>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    /**
     * @return 'multiple_operations'|null
     */
    private function reason(Expr $condition): ?string
    {
        $operations = $this->finder->find($condition, $this->isDecisionOperation(...));
        $hasMultipleOperations = count($operations) > 1;

        return $hasMultipleOperations ? 'multiple_operations' : null;
    }

    /**
     * Recognize operations that contribute work to a control-flow predicate.
     */
    private function isDecisionOperation(Node $node): bool
    {
        return $node instanceof Expr\BinaryOp
            || $node instanceof Expr\CallLike
            || $node instanceof Expr\BooleanNot
            || $node instanceof Expr\Instanceof_
            || $node instanceof Expr\Isset_
            || $node instanceof Expr\Empty_;
    }
}
