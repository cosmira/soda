<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Breathing;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\BreathingChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MinCodeBreathingScore implements RuleChecker
{
    public function __construct(private int $limit) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['min_code_breathing_score' => $this->limit]);

        return (new BreathingChecker)->check($context->withConfig($config));
    }
}
