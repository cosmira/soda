<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Rules\Complexity\ComplexControlConditionVisitor;
use Cosmira\Soda\Rules\Complexity\ElseBranchVisitor;
use Cosmira\Soda\Rules\Naming\CompoundVariableNameVisitor;
use Cosmira\Soda\Rules\Naming\NamingVisitor;
use Cosmira\Soda\Rules\Structure\TellDontAskVisitor;
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
 */
final class AstFacts
{
    /**
     * @param list<Node>        $nodes
     * @param list<string>|null $required
     *
     * @return FileMetrics
     */
    public static function collect(array $nodes, LogicalLineMap $lineMap, ?array $required): array
    {
        $cyclomaticScan = self::isRequired($required, 'complexity') ? new ComplexityVisitor() : null;
        $structureScan = new StructureVisitor($lineMap->count(), $lineMap);
        $callableScan = self::isRequired($required, 'nesting', 'returns', 'conditions', 'tryCatch', 'emptyCatches')
            ? new CallableMetricsVisitor() : null;
        $complexConditionScan = self::isRequired($required, 'conditions') ? new ComplexControlConditionVisitor() : null;
        $elseBranchScan = self::isRequired($required, 'elseBranches') ? new ElseBranchVisitor() : null;
        $tellDontAsk = self::isRequired($required, 'askThenTell') ? new TellDontAskVisitor() : null;
        $efferentCouplingVisitor = self::isRequired($required, 'coupling') ? new EfferentCouplingVisitor() : null;
        $namingVisitor = self::isRequired($required, 'naming') ? new NamingVisitor() : null;
        $compoundNames = self::isRequired($required, 'naming') ? new CompoundVariableNameVisitor() : null;

        $traverser = new NodeTraverser(new NameResolver(), new ParentConnectingVisitor(), ...array_filter([
            $cyclomaticScan, $structureScan, $callableScan,
            $complexConditionScan, $elseBranchScan, $tellDontAsk,
            $efferentCouplingVisitor, $namingVisitor, $compoundNames,
        ]));
        $traverser->traverse($nodes);

        $metrics = $structureScan->metrics();

        $metrics['classes'] = self::classMetrics($metrics['classes'], $efferentCouplingVisitor);

        $metrics['naming'] = ($namingVisitor?->facts() ?? []);
        $metrics['compoundVariableNames'] = ($compoundNames?->occurrences() ?? []);
        $metrics['emptyCatches'] = ($callableScan?->emptyCatches() ?? []);
        $metrics['elseBranches'] = ($elseBranchScan?->occurrences() ?? []);
        $metrics['complexControlConditions'] = ($complexConditionScan?->occurrences() ?? []);
        $metrics['askThenTell'] = ($tellDontAsk?->violations() ?? []);

        $metrics['methods'] = self::methodMetrics($metrics['methods'], $cyclomaticScan, $callableScan);

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
