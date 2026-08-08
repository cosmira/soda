<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Structural;

use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\MultilinePhpDocChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class MultilineMethodPhpDoc implements RuleChecker
{
    /**
     * @param list<'public'|'protected'|'private'> $visibilities
     */
    public function __construct(
        private array $visibilities = ['public'],
        private int $maxViolations = 0,
    ) {}

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        return (new MultilinePhpDocChecker)
            ->check($context->withConfig($this->config()));
    }

    private function config(): QualityConfig
    {
        return new QualityConfig(
            rules: [MultilinePhpDocChecker::METHOD_RULE => $this->maxViolations],
            ruleState: new QualityConfigRuleState(options: [
                MultilinePhpDocChecker::METHOD_RULE => ['visibilities' => $this->visibilities],
            ]),
        );
    }
}
