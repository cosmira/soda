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
final class ReadabilityMetricDefinitions
{
    public static function entries(string $key): array
    {
        return [
            RuleDefinition::of(
                new RuleIdentity('min_identifier_readability_score', $key),
                new RulePresentation('Identifier Readability Score:', 'warning', 'min'),
                new RuleScoring(100)
            ),

            RuleDefinition::of(
                new RuleIdentity('min_code_oxygen_level', $key),
                new RulePresentation('Code Oxygen Level:', 'warning', 'min'),
                new RuleScoring(100)
            ),
        ];
    }
}
