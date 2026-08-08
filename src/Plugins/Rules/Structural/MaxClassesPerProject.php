<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\ProjectChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MaxClassesPerProject implements RuleChecker
{
    public function __construct(private int $limit) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_classes_per_project' => $this->limit]);

        return (new ProjectChecker)->check($context->withConfig($config));
    }
}
