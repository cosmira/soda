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
final class ReadabilityScoreDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(
                new RuleIdentity('min_code_breathing_score', $sectionKey),
                new RulePresentation('Code Breathing Score:', 'warning', 'min'),
                new RuleScoring(100)
            ),

            RuleDefinition::of(
                new RuleIdentity('min_visual_breathing_index', $sectionKey),
                new RulePresentation('Visual Breathing Index:', 'warning', 'min'),
                new RuleScoring(70)
            ),
        ];
    }
}
