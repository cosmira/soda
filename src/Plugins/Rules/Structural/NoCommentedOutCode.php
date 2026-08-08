<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\CommentedCodeChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class NoCommentedOutCode implements RuleChecker
{
    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_commented_out_code_lines' => 0]);

        return (new CommentedCodeChecker)->check($context->withConfig($config));
    }
}
