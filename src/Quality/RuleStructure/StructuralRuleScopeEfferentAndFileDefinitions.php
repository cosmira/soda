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
final class StructuralRuleScopeEfferentAndFileDefinitions
{
    public static function entries(string $section): array
    {
        return [
            RuleDefinition::of(
                new RuleIdentity('max_efferent_coupling', $section),
                new RulePresentation('Efferent coupling (Ce):', 'warning'),
                new RuleScoring(10)
            ),

            RuleDefinition::of(
                new RuleIdentity('max_classes_per_file', $section),
                new RulePresentation('Classes per file:', 'warning'),
                new RuleScoring(1)
            ),
        ];
    }
}
