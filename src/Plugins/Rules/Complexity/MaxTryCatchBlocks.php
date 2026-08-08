<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Complexity;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\MethodRules;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MaxTryCatchBlocks implements RuleChecker
{
    public function __construct(private int $limit) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_try_catch_blocks' => $this->limit]);

        return (new MethodRules)->check($context->withConfig($config));
    }
}
