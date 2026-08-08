<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Naming;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\RedundantNamingChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class AvoidRedundantNaming implements RuleChecker
{
    public function __construct(private int $limit) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['avoid_redundant_naming' => $this->limit]);

        return (new RedundantNamingChecker)->check($context->withConfig($config));
    }
}
