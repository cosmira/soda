<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;

/** Collects flow facts with one shared stack of named and anonymous callables. */
final class CallableMetricsVisitor extends FactVisitor
{
    use CallableScopes;

    /**
     * @var array<string, int>
     */
    private array $returns = [];

    /**
     * @var array<string, int>
     */
    private array $tryCatch = [];

    /**
     * @var list<array{line: int, method: string|null, class: string|null}>
     */
    private array $empty = [];

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private readonly NestingTracker $tracker = new NestingTracker(),
        private readonly ConditionRecorder $recorder = new ConditionRecorder(),
    ) {}

    /**
     * Collect facts before traversing the children of this syntax node.
     */
    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        if ($node instanceof FunctionLike) {
            $this->openCallable($node);

            return;
        }

        if (AnonymousClassBoundary::isInside($node)) {
            return;
        }

        if (ControlStructureMatcher::isControlStructure($node)) {
            $this->tracker->pushControl($node->getStartLine());
        }

        $this->recordStatements($node);
        $this->recordEmptyCatch($node);
    }

    /**
     * Suspend the outer callable and initialize facts for the new lexical scope.
     */
    private function openCallable(FunctionLike $node): void
    {
        $isNamed = $node instanceof ClassMethod || $node instanceof Function_;
        if (! $isNamed) {
            $this->enterClosure();
            $this->tracker->enterClosure();

            return;
        }

        $name = $this->enterMethodScope($node);
        if ($name === null) {
            $this->tracker->enterClosure();

            return;
        }

        $this->tracker->startMethod($name, $node->getStartLine());
        $this->returns[$name] = 0;
        $this->tryCatch[$name] = 0;
        $this->recorder->openMethod($name);
    }

    /**
     * Attribute condition, return and try/catch counts only to the current named callable.
     */
    private function recordStatements(Node $node): void
    {
        $name = $this->currentMethod();
        if ($name === null) {
            return;
        }

        $this->recorder->record($node, $name);
        if ($node instanceof Return_) {
            $this->returns[$name]++;
        }

        if ($node instanceof TryCatch) {
            $this->tryCatch[$name]++;
        }
    }

    /**
     * Record an empty catch with its named owner, or null for an anonymous or file scope.
     */
    private function recordEmptyCatch(Node $node): void
    {
        $isEmpty = $node instanceof Catch_ && $node->stmts === [];
        if (! $isEmpty) {
            return;
        }

        $method = $this->currentMethod();
        $isClassMethod = $method !== null && str_contains($method, '::');
        $this->empty[] = [
            'line'   => $node->getStartLine(),
            'method' => $method,
            'class'  => $isClassMethod ? strstr($method, '::', true) : null,
        ];
    }

    /**
     * Restore analysis state after traversing the children of this syntax node.
     */
    #[\Override]
    protected function doLeaveNode(Node $node): void
    {
        if ($node instanceof FunctionLike) {
            $this->leaveMethodScope();
            $this->tracker->endMethod();

            return;
        }

        if (AnonymousClassBoundary::isInside($node)) {
            return;
        }

        if (ControlStructureMatcher::isControlStructure($node)) {
            $this->tracker->popControl();
        }
    }

    /**
     * @return array<string, array{depth: int, line: int}>
     */
    public function nestingByMethod(): array
    {
        return $this->tracker->nestingByMethod();
    }

    /**
     * @return array<string, int>
     */
    public function returnsByMethod(): array
    {
        return $this->returns;
    }

    /**
     * @return array<string, int>
     */
    public function tryCatchCountsByMethod(): array
    {
        return $this->tryCatch;
    }

    /**
     * @return array<string, list<array{line: int, count: int}>>
     */
    public function booleanConditionsByMethod(): array
    {
        return $this->recorder->conditionsByMethod();
    }

    /**
     * @return list<array{line: int, method: string|null, class: string|null}>
     */
    public function emptyCatches(): array
    {
        return $this->empty;
    }
}
