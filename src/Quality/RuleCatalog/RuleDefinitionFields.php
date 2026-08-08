<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleCatalog;

final readonly class RuleDefinitionFields
{
    public function __construct(
        public RuleIdentity $identity,
        public RulePresentation $presentation,
        public RuleScoring $scoring,
        public ?RuleInitDefinition $init = null,
    ) {}
}
