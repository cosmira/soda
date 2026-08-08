<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleReadability;

use Bunnivo\Soda\Quality\RuleCatalog\RuleDefinition;

/**
 * @internal
 *
 * @return list<RuleDefinition>
 */
final class ComplexityRuleDefinitions
{
    public static function all(string $section): array
    {
        return [
            ...ComplexityRuleCycleDefinitions::entries($section),

            ...ComplexityRuleDensityDefinitions::entries($section),

            ...ComplexityRuleFlowDefinitions::entries($section),
        ];
    }
}
