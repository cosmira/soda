<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleReadability;

use Bunnivo\Soda\Quality\RuleCatalog\RuleDefinition;

/**
 * @internal
 *
 * @return list<RuleDefinition>
 */
final class ReadabilityRuleDefinitions
{
    public static function all(string $section): array
    {
        return [
            ...ReadabilityScoreDefinitions::entries($section),

            ...ReadabilityMetricDefinitions::entries($section),
        ];
    }
}
