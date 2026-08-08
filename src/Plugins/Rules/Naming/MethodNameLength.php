<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Naming;

use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\NameLengthChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MethodNameLength implements RuleChecker
{
    public function __construct(private int $min = 3, private int $max = 32) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        return (new NameLengthChecker)
            ->check($context->withConfig($this->config(NameLengthChecker::METHOD)));
    }

    private function config(string $rule): QualityConfig
    {
        return new QualityConfig(
            rules: [$rule => $this->max],
            ruleState: new QualityConfigRuleState(options: [$rule => ['min' => $this->min, 'max' => $this->max]]),
        );
    }
}
