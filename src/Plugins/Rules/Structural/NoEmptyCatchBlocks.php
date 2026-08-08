<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\EmptyCatchChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class NoEmptyCatchBlocks implements RuleChecker
{
    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_empty_catch_blocks' => 0]);

        return (new EmptyCatchChecker)->check($context->withConfig($config));
    }
}
