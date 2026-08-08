<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Bunnivo\Soda\Quality\Rule\TodoCommentChecker;
use Illuminate\Support\Collection;

final readonly class NoTodoFixmeComments implements RuleChecker
{
    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(rules: ['max_todo_fixme_comments' => 0]);

        return (new TodoCommentChecker)->check($context->withConfig($config));
    }
}
