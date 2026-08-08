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
final class StructuralRuleScopeNamespaceDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(new RuleIdentity('max_namespace_depth', $sectionKey), new RulePresentation('Namespace depth:', 'warning'), new RuleScoring(4)),

            RuleDefinition::of(new RuleIdentity('max_classes_per_namespace', $sectionKey), new RulePresentation('Classes per namespace:', 'warning'), new RuleScoring(15)),

            RuleDefinition::of(new RuleIdentity('max_layer_dominance_percentage', $sectionKey), new RulePresentation('Layer mixing:', 'error'), new RuleScoring(50)),
        ];
    }
}
