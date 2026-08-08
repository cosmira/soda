<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleReadability;

use Bunnivo\Soda\Quality\RuleCatalog\RuleDefinition;
use Bunnivo\Soda\Quality\RuleCatalog\RuleIdentity;
use Bunnivo\Soda\Quality\RuleCatalog\RulePresentation;
use Bunnivo\Soda\Quality\RuleCatalog\RuleScoring;

/**
 * @internal
 *
 * @return list<RuleDefinition>
 */
final class ComplexityRuleDensityDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(new RuleIdentity('max_weighted_cognitive_density', $sectionKey), new RulePresentation('Weighted Cognitive Density:', 'warning', 'max'), new RuleScoring(60)),

            RuleDefinition::of(new RuleIdentity('max_logical_complexity_factor', $sectionKey), new RulePresentation('Logical Complexity Factor:', 'warning', 'max'), new RuleScoring(50)),
        ];
    }
}
