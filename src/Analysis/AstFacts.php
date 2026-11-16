<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Analysis\Complexity\ComplexControlConditionVisitor;
use Cosmira\Soda\Analysis\Complexity\ElseBranchVisitor;
use Cosmira\Soda\Analysis\Naming\CompoundVariableNameVisitor;
use Cosmira\Soda\Analysis\Naming\NamingVisitor;
use Cosmira\Soda\Analysis\Structure\TellDontAskVisitor;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * Resolve syntax once and collect requested AST facts without exposing visitors.
 *
 * @phpstan-import-type ClassFacts from FileFacts
 * @phpstan-import-type MethodFacts from FileFacts
 * @phpstan-import-type FileMetrics from FileFacts
 *
 * @phpstan-type ScanFacts array{
 *   complexity: ComplexityVisitor|null,
 *   structure: StructureVisitor,
 *   callables: CallableMetricsVisitor|null,
 *   conditions: ComplexControlConditionVisitor|null,
 *   elseBranches: ElseBranchVisitor|null,
 *   tellDontAsk: TellDontAskVisitor|null,
 *   coupling: EfferentCouplingVisitor|null,
 *   naming: NamingVisitor|null,
 *   compoundNames: CompoundVariableNameVisitor|null
 * }
 */
final class AstFacts
{
    use CollectsDesignFacts;

    /**
     * Collect only the requested metric families from the parsed syntax tree.
     *
     * @param list<Node>        $nodes
     * @param list<string>|null $required
     *
     * @return FileMetrics
     */
    public static function collect(array $nodes, LogicalLineMap $lineMap, ?array $required, string $source = ''): array
    {
        $scans = self::traverseScans($nodes, $lineMap, $required, $source);

        $metrics = self::scannedMetrics($scans);

        return self::designMetrics($metrics, $nodes, $required);
    }

    /**
     * Construct only the requested visitors and run them once over resolved syntax.
     *
     * @param list<Node>        $nodes
     * @param list<string>|null $required
     *
     * @return ScanFacts
     */
    private static function traverseScans(array $nodes, LogicalLineMap $lineMap, ?array $required, string $source = ''): array
    {
        $scans = [
            'complexity'     => self::isRequired($required, 'complexity') ? new ComplexityVisitor() : null,
            'structure'      => new StructureVisitor($lineMap->count(), $lineMap),
            'callables'      => self::isRequired($required, 'nesting', 'returns', 'conditions', 'tryCatch', 'emptyCatches')
                ? new CallableMetricsVisitor() : null,
            'conditions'     => self::isRequired($required, 'conditions') ? new ComplexControlConditionVisitor($source) : null,
            'elseBranches'   => self::isRequired($required, 'elseBranches') ? new ElseBranchVisitor() : null,
            'tellDontAsk'    => self::isRequired($required, 'askThenTell') ? new TellDontAskVisitor() : null,
            'coupling'       => self::isRequired($required, 'coupling') ? new EfferentCouplingVisitor() : null,
            'naming'         => self::isRequired($required, 'naming') ? new NamingVisitor() : null,
            'compoundNames'  => self::isRequired($required, 'naming') ? new CompoundVariableNameVisitor() : null,
        ];

        $traverser = new NodeTraverser(new NameResolver(), new ParentConnectingVisitor(), ...array_values(array_filter($scans)));
        $traverser->traverse($nodes);

        return $scans;
    }

    /**
     * Assemble the structural metrics with each executed scan's optional measurements.
     *
     * @param ScanFacts $scans
     *
     * @return FileMetrics
     */
    private static function scannedMetrics(array $scans): array
    {
        $metrics = $scans['structure']->metrics();

        $metrics['classes'] = self::classMetrics($metrics['classes'], $scans['coupling']);
        $metrics['typeDependencies'] = $scans['coupling']?->dependenciesByClass() ?? [];

        $metrics['naming'] = ($scans['naming']?->facts() ?? []);
        $metrics['compoundVariableNames'] = ($scans['compoundNames']?->occurrences() ?? []);
        $metrics['emptyCatches'] = ($scans['callables']?->emptyCatches() ?? []);
        $metrics['elseBranches'] = ($scans['elseBranches']?->occurrences() ?? []);
        $metrics['complexControlConditions'] = ($scans['conditions']?->occurrences() ?? []);
        $metrics['askThenTell'] = ($scans['tellDontAsk']?->violations() ?? []);

        $metrics['methods'] = self::methodMetrics($metrics['methods'], $scans['complexity'], $scans['callables']);

        return $metrics;
    }

    /**
     * Merge class dependencies with the structure already collected from this AST.
     *
     * @param array<string, ClassFacts> $classes
     *
     * @return array<string, ClassFacts>
     */
    private static function classMetrics(array $classes, ?EfferentCouplingVisitor $coupling): array
    {
        foreach (($coupling?->couplingCountsByClass() ?? []) as $className => $ce) {
            if (isset($classes[$className])) {
                $entry = $classes[$className];
                $entry['efferent_coupling'] = $ce;
                $classes[$className] = $entry;
            }
        }

        foreach ($coupling?->dependenciesByClass() ?? [] as $name => $dependencies) {
            if (isset($classes[$name])) {
                $entry = $classes[$name];
                $entry['depends_on'] = $dependencies;
                $classes[$name] = $entry;
            }
        }

        return $classes;
    }

    /**
     * Add callable measurements to the existing method rows.
     *
     * @param array<string, MethodFacts> $methods
     *
     * @return array<string, MethodFacts>
     */
    private static function methodMetrics(array $methods, ?ComplexityVisitor $cyclomaticScan, ?CallableMetricsVisitor $callableScan): array
    {
        $complexity = $cyclomaticScan?->complexity() ?? [];

        $nesting = $callableScan?->nestingByMethod() ?? [];
        $returns = $callableScan?->returnsByMethod() ?? [];
        $catches = $callableScan?->tryCatchCountsByMethod() ?? [];
        foreach ($methods as $name => &$method) {
            $method['complexity'] = $complexity[$name] ?? 1;
            $depth = $nesting[$name] ?? [];
            $method['nesting'] = $depth['depth'] ?? 0;
            $method['returns'] = $returns[$name] ?? 0;
            $method['try_catch'] = $catches[$name] ?? 0;
            $method['nesting_line'] = $depth['line'] ?? null;
            $method['boolean_conditions'] = $callableScan?->booleanConditionsByMethod()[$name] ?? [];
        }

        unset($method);

        return $methods;
    }

    /**
     * Null requests every analysis; otherwise construct only the requested visitors.
     */
    private static function isRequired(?array $required, string ...$analyses): bool
    {
        return $required === null || array_intersect($required, $analyses) !== [];
    }
}
