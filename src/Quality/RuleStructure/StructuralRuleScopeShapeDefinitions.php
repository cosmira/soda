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
final class StructuralRuleScopeShapeDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(new RuleIdentity('max_traits_per_class', $sectionKey), new RulePresentation('Traits per class:', 'warning'), new RuleScoring(10)),

            RuleDefinition::of(new RuleIdentity('max_interfaces_per_class', $sectionKey), new RulePresentation('Interfaces per class:', 'warning'), new RuleScoring(5)),

            RuleDefinition::of(new RuleIdentity('max_classes_per_project', $sectionKey), new RulePresentation('Classes per project:', 'error'), new RuleScoring(300)),
        ];
    }
}
