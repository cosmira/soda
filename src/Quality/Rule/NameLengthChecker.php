<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Limits;
use Bunnivo\Soda\Quality\Report\Violation;
use Bunnivo\Soda\Quality\Report\ViolationBuilder;
use Illuminate\Support\Collection;
final class NameLengthChecker implements RuleChecker
{
    public const string VARIABLE = 'variable_name_length';

    public const string METHOD = 'method_name_length';

    public const string CLASS_LIKE = 'class_name_length';

    private const array DEFAULT_MIN = [
        self::VARIABLE   => 3,
        self::METHOD     => 3,
        self::CLASS_LIKE => 3,
    ];

    private const array SUPERGLOBALS = [
        'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE',
        '_SESSION', '_REQUEST', '_ENV',
    ];

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        $rules = array_filter(
            [self::VARIABLE, self::METHOD, self::CLASS_LIKE],
            fn (string $rule): bool => $context->config->isRuleEnabled($rule),
        );

        if ($rules === []) {
            return collect();
        }

        $violations = [];

        $scanner = new NameLengthNodeScanner();

        foreach (array_keys($context->fileMetrics->qualityMetrics()) as $file) {
            foreach ($scanner->scan($file, $rules) as $occurrence) {
                array_push($violations, ...$this->violationsForOccurrence($file, $context, $occurrence));
            }
        }

        return collect($violations);
    }

    /**
     * @return list<Violation>
     */
    private function violationsForOccurrence(
        string $file,
        EvaluationContext $context,
        NameLengthOccurrence $occurrence,
    ): array {
        if ($occurrence->name === 'this' || in_array($occurrence->name, self::SUPERGLOBALS, true)) {
            return [];
        }

        $length = strlen($occurrence->name);
        $min = $this->min($occurrence->rule, $context);
        $max = $this->max($occurrence->rule, $context);

        if ($length < $min) {
            return [$this->violation($occurrence->rule, $file, $occurrence->line, $length, $min)];
        }

        if ($length > $max) {
            return [$this->violation($occurrence->rule, $file, $occurrence->line, $length, $max)];
        }

        return [];
    }

    private function min(string $rule, EvaluationContext $context): int
    {
        $options = $context->config->ruleState->options[$rule] ?? [];

        return (int) ($options['min'] ?? self::DEFAULT_MIN[$rule]);
    }

    private function max(string $rule, EvaluationContext $context): int
    {
        $options = $context->config->ruleState->options[$rule] ?? [];

        return (int) ($options['max'] ?? $context->config->getRule($rule));
    }

    private function violation(
        string $rule,
        string $file,
        int $line,
        int $length,
        int $threshold,
    ): Violation {
        return ViolationBuilder::of($rule, $file, new Limits($length, $threshold))
            ->atLine($line)
            ->build();
    }
}
