<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleCatalog;

use Bunnivo\Soda\Quality\RuleReadability\ComplexityRuleDefinitions;
use Bunnivo\Soda\Quality\RuleReadability\ReadabilityRuleDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\NamingRuleDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleLengthDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleScopeEfferentAndFileDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleScopeNamespaceDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleScopeShapeDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleSmellDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleSurfaceApiDefinitions;
use Bunnivo\Soda\Quality\RuleStructure\StructuralRuleSurfaceSizeDefinitions;

/**
 * Single source of truth for rule ids, default thresholds, presentation metadata, and config sections.
 */
final class RuleCatalog
{
    /**
     * @return array<string, RuleInitDefinition>
     */
    public static function initDefinitions(): array
    {
        $out = [];

        foreach (self::definitions() as $id => $definition) {
            if ($definition->fields->init !== null) {
                $out[$id] = $definition->fields->init;
            }
        }

        return $out;
    }

    /**
     * @return array<string, RuleInitDefinition>
     */
    private static function initDefinitionMap(): array
    {
        return [
            'max_file_loc'                   => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxFileLoc', 'int'),
            'max_line_length'                => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxLineLength', 'int'),
            'max_classes_per_file'           => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxClassesPerFile', 'int'),
            'max_method_length'              => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength', 'int'),
            'max_class_length'               => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxClassLength', 'int'),
            'max_arguments'                  => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxArguments', 'int'),
            'max_methods_per_class'          => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodsPerClass', 'int'),
            'max_properties_per_class'       => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxPropertiesPerClass', 'int'),
            'max_public_methods'             => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxPublicMethods', 'int'),
            'max_dependencies'               => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxDependencies', 'int'),
            'max_efferent_coupling'          => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxEfferentCoupling', 'int'),
            'max_traits_per_class'           => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxTraitsPerClass', 'int'),
            'max_interfaces_per_class'       => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxInterfacesPerClass', 'int'),
            'max_todo_fixme_comments'        => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\NoTodoFixmeComments', 'none'),
            'max_commented_out_code_lines'   => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\NoCommentedOutCode', 'none'),
            'max_empty_catch_blocks'         => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\NoEmptyCatchBlocks', 'none'),
            'max_ask_then_tell_patterns'     => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\NoAskThenTellPatterns', 'none'),
            'methods_follow_call_order'       => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MethodsFollowCallOrder', 'int'),
            'multiline_method_phpdoc'        => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MultilineMethodPhpDoc', 'visibility'),
            'multiline_property_phpdoc'      => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MultilinePropertyPhpDoc', 'visibility'),
            'only_list_arrays'               => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\ListOnlyArray\OnlyListArraysAllowed', 'none'),
            'no_numeric_array_index'         => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\NumericArrayIndex\NoNumericArrayIndex', 'none'),
            'unused_methods'                 => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\NoUnusedMethods', 'none'),
            'useless_variable'               => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\UselessVariableRule', 'none'),
            'max_layer_dominance_percentage' => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxLayerDominancePercentage', 'layer'),
            'max_namespace_depth'            => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxNamespaceDepth', 'int'),
            'max_classes_per_namespace'      => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxClassesPerNamespace', 'int'),
            'max_classes_per_project'        => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Structural\MaxClassesPerProject', 'int'),

            'max_cyclomatic_complexity'      => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxCyclomaticComplexity', 'int'),
            'max_control_nesting'            => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxControlNesting', 'int'),
            'max_return_statements'          => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxReturnStatements', 'int'),
            'max_boolean_conditions'         => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxBooleanConditions', 'int'),
            'max_try_catch_blocks'           => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxTryCatchBlocks', 'int'),
            'max_weighted_cognitive_density' => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxWeightedCognitiveDensity', 'int'),
            'max_logical_complexity_factor'  => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Complexity\MaxLogicalComplexityFactor', 'int'),

            'min_code_breathing_score'         => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Breathing\MinCodeBreathingScore', 'int'),
            'min_visual_breathing_index'       => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Breathing\MinVisualBreathingIndex', 'int'),
            'min_identifier_readability_score' => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Breathing\MinIdentifierReadabilityScore', 'int'),
            'min_code_oxygen_level'            => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Breathing\MinCodeOxygenLevel', 'int'),

            'avoid_redundant_naming'         => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Naming\AvoidRedundantNaming', 'int'),
            'boolean_methods_without_prefix' => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Naming\BooleanMethodPrefix', 'none'),
            'variable_name_length'           => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Naming\VariableNameLength', 'range', 3),
            'method_name_length'             => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Naming\MethodNameLength', 'range', 3),
            'class_name_length'              => new RuleInitDefinition('Bunnivo\Soda\Plugins\Rules\Naming\ClassNameLength', 'range', 3),
        ];
    }

    /**
     * @return array<string, RuleDefinition>
     */
    public static function definitions(): array
    {
        $byId = [];
        $initDefinitions = self::initDefinitionMap();

        foreach (self::orderedDefinitions() as $definition) {
            $id = $definition->fields->identity->id;
            $init = $initDefinitions[$id] ?? null;
            $byId[$id] = $init !== null ? $definition->withInit($init) : $definition;
        }

        return $byId;
    }

    /**
     * Section → ordered rule ids (matches nested `rules` section layout).
     *
     * @return array<string, list<string>>
     */
    public static function sectionsOrdered(): array
    {
        $out = [];

        foreach (self::orderedDefinitions() as $definition) {
            $identity = $definition->fields->identity;
            $section = $identity->section;
            $list = $out[$section] ?? [];
            $list[] = $identity->id;
            $out[$section] = $list;
        }

        return $out;
    }

    /**
     * @return array<string, int|float>
     */
    public static function defaultThresholds(): array
    {
        $thresholds = [];

        foreach (self::definitions() as $id => $definition) {
            $thresholds[$id] = $definition->fields->scoring->defaultThreshold;
        }

        return $thresholds;
    }

    /**
     * @return array<string, array{severity: 'error'|'warning', label: string, comparison?: 'min'|'max'}>
     */
    public static function metadataMap(): array
    {
        $map = [];

        foreach (self::definitions() as $id => $definition) {
            $map[$id] = $definition->toMetadataEntry();
        }

        return $map;
    }

    /**
     * @param list<string> $ruleIds
     *
     * @return array<string, array{severity: 'error'|'warning', label: string, comparison?: 'min'|'max'}>
     */
    public static function metadataForRules(array $ruleIds): array
    {
        $all = self::metadataMap();

        $slice = [];

        foreach ($ruleIds as $id) {
            if (isset($all[$id])) {
                $slice[$id] = $all[$id];
            }
        }

        return $slice;
    }

    /**
     * @return list<RuleDefinition>
     */
    private static function orderedDefinitions(): array
    {
        $sec = [
            'structural' => 'structural',
            'complexity' => 'complexity',
            'breathing'  => 'breathing',
            'naming'     => 'naming',
        ];

        return [
            ...StructuralRuleLengthDefinitions::entries($sec['structural']),
            ...StructuralRuleSurfaceSizeDefinitions::entries($sec['structural']),
            ...StructuralRuleSurfaceApiDefinitions::entries($sec['structural']),
            ...StructuralRuleSmellDefinitions::entries($sec['structural']),
            ...StructuralRuleScopeEfferentAndFileDefinitions::entries($sec['structural']),
            ...StructuralRuleScopeNamespaceDefinitions::entries($sec['structural']),
            ...StructuralRuleScopeShapeDefinitions::entries($sec['structural']),
            ...ComplexityRuleDefinitions::all($sec['complexity']),
            ...ReadabilityRuleDefinitions::all($sec['breathing']),
            ...NamingRuleDefinitions::all($sec['naming']),
        ];
    }
}
