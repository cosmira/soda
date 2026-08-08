<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\Naming;

use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\BooleanMethodPrefixChecker;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Illuminate\Support\Collection;

final readonly class BooleanMethodPrefix implements RuleChecker
{
    private const array DEFAULT_IGNORE = [
        'create', 'update', 'delete', 'execute', 'run', 'process',
        'handle', 'dispatch', 'send', 'save', 'store', 'generate', 'build', 'check',
    ];

    private const array DEFAULT_PREFIX = [
        'is', 'has', 'can', 'should', 'does', 'was',
        'are', 'do', 'did', 'will', 'try',
    ];

    private array $ignore;

    private array $prefix;

    /**
     * @param string[] $ignore Method names to skip.
     * @param string[] $prefix Allowed prefixes (defaults cover the most common ones).
     */
    public function __construct(
        array $ignore = [],
        array $prefix = [],
    ) {
        $this->ignore = array_merge(self::DEFAULT_IGNORE, $ignore);
        $this->prefix = array_merge(self::DEFAULT_PREFIX, $prefix);
    }

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $config = new QualityConfig(
            rules: [BooleanMethodPrefixChecker::RULE => 0],
            ruleState: new QualityConfigRuleState(
                exceptions: [BooleanMethodPrefixChecker::RULE => [
                    'methods'  => $this->ignore,
                    'prefixes' => $this->prefix,
                ]],
            ),
        );

        return (new BooleanMethodPrefixChecker)
            ->check($context->withConfig($config));
    }
}
