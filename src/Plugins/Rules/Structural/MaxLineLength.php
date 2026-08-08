<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\LineLengthChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MaxLineLength implements RuleChecker
{
    public function __construct(private int $limit = 100) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_line_length' => $this->limit]);

        return (new LineLengthChecker)->check($context->withConfig($config));
    }
}
