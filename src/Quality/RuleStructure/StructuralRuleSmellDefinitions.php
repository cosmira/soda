<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleStructure;

use Bunnivo\Soda\Quality\RuleCatalog\RuleDefinition;
use Bunnivo\Soda\Quality\RuleCatalog\RuleIdentity;
use Bunnivo\Soda\Quality\RuleCatalog\RulePresentation;
use Bunnivo\Soda\Quality\RuleCatalog\RuleScoring;

/**
 * @internal
 *
 * @return list<RuleDefinition>
 */
final class StructuralRuleSmellDefinitions
{
    public static function entries(string $sectionKey): array
    {
        return [
            self::warningRule('max_todo_fixme_comments', $sectionKey, 'TODO/FIXME comments:'),
            self::warningRule('max_commented_out_code_lines', $sectionKey, 'Commented-out code lines:'),
            self::warningRule('max_empty_catch_blocks', $sectionKey, 'Empty catch blocks:'),
            self::warningRule('max_ask_then_tell_patterns', $sectionKey, 'Ask-then-tell patterns:'),
            self::warningRule('methods_follow_call_order', $sectionKey, 'Methods out of call order:'),
            self::warningRule('multiline_method_phpdoc', $sectionKey, 'Methods without multiline PHPDoc:'),
            self::warningRule('multiline_property_phpdoc', $sectionKey, 'Properties without multiline PHPDoc:'),
            self::warningRule('only_list_arrays', $sectionKey, 'Nested array chains (list-only / strict opt-in):'),
            self::warningRule('no_numeric_array_index', $sectionKey, 'Numeric array index:'),
            self::warningRule('unused_methods', $sectionKey, 'Unused methods:'),
            self::warningRule('useless_variable', $sectionKey, 'Useless variable:'),
        ];
    }

    private static function warningRule(string $ruleId, string $sectionKey, string $label): RuleDefinition
    {
        return RuleDefinition::of(
            new RuleIdentity($ruleId, $sectionKey),
            new RulePresentation($label, 'warning'),
            new RuleScoring(0),
        );
    }
}
