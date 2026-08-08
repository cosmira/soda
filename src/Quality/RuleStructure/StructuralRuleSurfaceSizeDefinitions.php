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
final class StructuralRuleSurfaceSizeDefinitions
{
    public static function entries(string $sectionKey): array
    {

        return [
            RuleDefinition::of(new RuleIdentity('max_file_loc', $sectionKey), new RulePresentation('File LOC:', 'warning'), new RuleScoring(700)),

            RuleDefinition::of(new RuleIdentity('max_line_length', $sectionKey), new RulePresentation('Line length:', 'warning'), new RuleScoring(100)),

            RuleDefinition::of(new RuleIdentity('max_properties_per_class', $sectionKey), new RulePresentation('Properties per class:', 'warning'), new RuleScoring(5)),
        ];
    }
}
