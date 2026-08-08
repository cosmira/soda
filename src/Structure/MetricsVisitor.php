<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Structure;

use function array_pop;

use Bunnivo\Soda\Visitor\NullableReturnVisitor;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * Collects structure metrics, dependencies, and LLOC breakdown via AST.
 *
 * @internal
 */
final class MetricsVisitor extends NullableReturnVisitor
{
    private readonly MetricsState $state;

    public function __construct()
    {
        $this->state = new MetricsState();
    }

    #[\Override]
    protected function doEnterNode(Node $node): void
    {
        $this->dispatchEnter($node);
    }

    #[\Override]
    protected function doLeaveNode(Node $node): void
    {
        if ($node->getType() !== 'Stmt_Class' && $node->getType() !== 'Stmt_Trait') {
            return;
        }

        if ($this->state->classStack === []) {
            return;
        }

        array_pop($this->state->classStack);
    }

    private function dispatchEnter(Node $node): void
    {
        StructureNodeHandlers::handleNamespace($this->state, $node);
        StructureNodeHandlers::handleTypeDeclarations($this->state, $node);
        StructureNodeHandlers::handleMembers($this->state, $node);
        StructureNodeHandlers::handleGlobals($this->state, $node);
        ExpressionHandlers::handle($this->state, $node);

        if ($node instanceof Variable) {
            NodeHandlers::handleVariable($this->state, $node);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function result(): array
    {
        return $this->state->toResult();
    }

    /**
     * @param array<string, array<string, mixed>> $results
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress PossiblyUnusedMethod used by tests and external callers
     */
    public static function merge(array $results): array
    {
        return MetricsMerger::merge($results);
    }

    /**
     * @param array<string, mixed> $stats
     *
     * @return array{
     *   llocClasses: int,
     *   llocFunctions: int,
     *   llocGlobal: int,
     *   classLlocMin: int,
     *   classLlocAvg: int,
     *   classLlocMax: int,
     *   methodLlocMin: int,
     *   methodLlocAvg: int,
     *   methodLlocMax: int,
     *   averageMethodsPerClass: int,
     *   minimumMethodsPerClass: int,
     *   maximumMethodsPerClass: int,
     *   averageFunctionLength: int
     * }
     *
     * @psalm-suppress PossiblyUnusedMethod used by tests and external callers
     */
    public static function computeStats(array $stats, int $lloc): array
    {
        return StatsCalculator::compute($stats, $lloc);
    }
}
