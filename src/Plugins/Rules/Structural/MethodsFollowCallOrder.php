<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\MethodOrderChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MethodsFollowCallOrder implements RuleChecker
{
    public function __construct(private int $maxViolations = 0) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        return (new MethodOrderChecker)
            ->check($context->withConfig(new QualityConfig(rules: [MethodOrderChecker::RULE => $this->maxViolations])));
    }
}
