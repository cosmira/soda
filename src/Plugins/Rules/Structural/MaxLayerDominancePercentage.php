<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\LayerMixingChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MaxLayerDominancePercentage implements RuleChecker
{
    public function __construct(
        private int $threshold,
        private int $minFiles = LayerMixingChecker::DEFAULT_MIN_FILES,
    ) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(
            rules: ['max_layer_dominance_percentage' => $this->threshold],
            ruleState: new QualityConfigRuleState(
                options: ['max_layer_dominance_percentage' => ['min_files' => $this->minFiles]],
            ),
        );

        return (new LayerMixingChecker)->check($context->withConfig($config));
    }
}
