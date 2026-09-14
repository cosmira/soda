<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Rules\Naming\CompoundVariableNameOccurrence;
use PhpParser\Node;

/**
 * Class/trait measurements and their declared relationships share one row.
 * Relationship fields are optional while base metrics are being collected.
 *
 * @phpstan-type ClassFacts array{
 *   method_groups?: int, cohesion_methods?: int, cohesion_unknown?: int, tcc_pairs?: int, tcc_possible_pairs?: int,
 *   kind: 'class'|'trait', line: int, loc: int, methods: int, properties: int,
 *   public_methods: int, dependencies: int, efferent_coupling: int,
 *   traits: int, interfaces: int, namespace: string, namespace_depth: int,
 *   depends_on?: list<string>, parent?: string,
 *   public_method_names?: list<string>, property_keys?: list<string>,
 *   trait_names?: list<string>, interface_names?: list<string>,
 *   public_trait_aliases?: list<string>, hidden_trait_methods?: list<string>
 * }
 * @phpstan-type MethodFacts array{
 *   line: int, loc: int, args: int, readonlyDataFields?: int, name?: string, cognitive_complexity?: int,
 *   cognitive_contributions?: list<array{line: int, increment: int, reason: string}>,
 *   complexity?: int, nesting?: int, nesting_line?: int|null,
 *   returns?: int, try_catch?: int,
 *   boolean_conditions?: list<array{line: int, count: int}>
 * }
 * @phpstan-type NamingMethod array{
 *   name: string, methodName: string, class: string|null,
 *   firstParamType: string|null, returnType: string|null, line: int,
 *   isPublic: bool, hasOverrideAttribute: bool
 * }
 * @phpstan-type NamingType array{
 *   name: string, kind: 'class'|'trait'|'interface', line: int,
 *   inherits: list<string>, methods: list<string>
 * }
 * @phpstan-type NamingFacts array{
 *   classes: list<array{class: string, line: int}>,
 *   methods: list<NamingMethod>, types: list<NamingType>
 * }
 * @phpstan-type LocatedCallable array{line: int, method: string|null, class: string|null}
 * @phpstan-type AskThenTell array{
 *   line: int, method: string|null, class: string|null,
 *   receiver: string, question: string, command: string
 * }
 *
 * @phpstan-import-type CognitiveRow from \Cosmira\Soda\Rules\Complexity\CognitiveComplexity
 * @phpstan-import-type ComplexCondition from \Cosmira\Soda\Rules\Complexity\ComplexControlConditionVisitor
 * @phpstan-import-type ElseBranch from \Cosmira\Soda\Rules\Complexity\ElseBranchVisitor
 * @phpstan-import-type Declaration from \Cosmira\Soda\Rules\Usage\UnusedMethodDeclarations
 *
 * @phpstan-type FileMetrics array{
 *   methodUsage?: list<Declaration>,
 *   cohesion?: array<string, array>,
 *   file_loc: int, classes_count: int, cognitiveExtras?: array<string, CognitiveRow>,
 *   classes: array<string, ClassFacts>, methods: array<string, MethodFacts>,
 *   namespaces: array<string, int>, interfaceParents: array<string, list<string>>,
 *   naming?: NamingFacts|array{}, compoundVariableNames?: list<CompoundVariableNameOccurrence>,
 *   emptyCatches?: list<LocatedCallable>, elseBranches?: list<ElseBranch>,
 *   complexControlConditions?: list<ComplexCondition>,
 *   askThenTell?: list<AskThenTell>,
 *   todoFixme?: list<array{line: int, kind: string, text: string}>,
 *   commentedCode?: list<array{line: int, text: string}>
 * }
 * @phpstan-type ExpressionRow array<string, int|string|null|list<string>|list<array{line: int, count: int}>|list<array{line: int, increment: int, reason: string}>>
 *
 * Optional fields describe collection stages: structure precedes optional
 * measurements, comments are collected after AST facts, and project storage
 * drops compound variable occurrences. An unrequested analysis yields an empty
 * list (or documented zero/base method metric), never an invented measurement.
 */
final readonly class FileFacts
{
    /**
     * Keep the source and AST only for the lifetime of the current file's checks.
     *
     * @param list<Node>  $nodes
     * @param FileMetrics $metrics
     */
    public function __construct(
        public string $path,
        public string $source,
        public array $nodes,
        public array $metrics,
    ) {}

    /**
     * Expose expression rows without duplicating the stored method/class facts.
     *
     * @return iterable<ExpressionRow>
     */
    public function rows(string $scope): iterable
    {
        if ($scope === 'file') {
            yield [
                'path'    => str_replace('\\', '/', $this->path),
                'loc'     => $this->metrics['file_loc'],
                'classes' => $this->metrics['classes_count'],
            ];

            return;
        }

        $key = $scope === 'class' ? 'classes' : 'methods';
        foreach ($this->metrics[$key] as $name => $row) {
            [$owner] = explode('::', $name);
            $separator = strrpos($owner, '\\');
            yield [
                ...$row,
                'path'      => str_replace('\\', '/', $this->path),
                'name'      => $name,
                'namespace' => $row['namespace'] ?? ($separator === false ? '' : substr($owner, 0, $separator)),
            ];
        }
    }
}
