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
final class StructuralRuleSurfaceApiDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(new RuleIdentity('max_public_methods', $sectionKey), new RulePresentation('Public methods:', 'warning'), new RuleScoring(20)),

            RuleDefinition::of(new RuleIdentity('max_dependencies', $sectionKey), new RulePresentation('Dependencies:', 'warning'), new RuleScoring(8)),
        ];
    }
}
