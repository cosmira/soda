<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Complexity;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\BreathingChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MaxLogicalComplexityFactor implements RuleChecker
{
    public function __construct(private int $limit) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_logical_complexity_factor' => $this->limit]);

        return (new BreathingChecker)->check($context->withConfig($config));
    }
}
