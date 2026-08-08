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
final class NamingRuleDefinitions
{
    public static function all(string $section): array
    {
        return [
            RuleDefinition::of(new RuleIdentity('avoid_redundant_naming', $section), new RulePresentation('Redundant naming:', 'warning'), new RuleScoring(80)),

            RuleDefinition::of(new RuleIdentity('boolean_methods_without_prefix', $section), new RulePresentation('Boolean method prefixes:', 'warning'), new RuleScoring(0)),

            RuleDefinition::of(new RuleIdentity('variable_name_length', $section), new RulePresentation('Variable name length:', 'warning'), new RuleScoring(16)),

            RuleDefinition::of(new RuleIdentity('method_name_length', $section), new RulePresentation('Method name length:', 'warning'), new RuleScoring(32)),

            RuleDefinition::of(new RuleIdentity('class_name_length', $section), new RulePresentation('Class name length:', 'warning'), new RuleScoring(32)),
        ];
    }
}
