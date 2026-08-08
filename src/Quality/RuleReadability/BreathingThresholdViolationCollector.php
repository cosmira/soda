<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleReadability;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Report\Violation;
use Bunnivo\Soda\Quality\Rule\BreathingChecker;
use Bunnivo\Soda\Quality\RuleChecker as FluentRuleChecker;

/**
 * @internal
 */
final class BreathingThresholdViolationCollector
{
    /**
     * @param array<string, float> $breathing
     *
     * @return list<Violation>
     */
    public static function collectBelowThresholds(EvaluationContext $context, string $file, array $breathing): array
    {
        $list = [];

        foreach (BreathingChecker::minRuleMap() as $rule => $key) {
            if (! $context->config->isRuleEnabled($rule)) {
                continue;
            }

            $threshold = (int) $context->config->getRule($rule);
            if ($threshold <= 0) {
                continue;
            }

            $value = $breathing[$key] ?? 0.0;
            $scaled = (int) round($value * 100.0);

            if ($scaled < $threshold) {
                foreach (FluentRuleChecker::whenBelow($rule)->file($file)->forValue($scaled)->limit($threshold)->result() as $v) {
                    $list[] = $v;
                }
            }
        }

        return $list;
    }

    /**
     * @param array<string, float> $breathing
     *
     * @return list<Violation>
     */
    public static function collectAboveThresholds(EvaluationContext $context, string $file, array $breathing): array
    {
        $list = [];

        foreach (BreathingChecker::maxRuleMap() as $rule => $key) {
            if (! $context->config->isRuleEnabled($rule)) {
                continue;
            }

            $threshold = (int) $context->config->getRule($rule);
            if ($threshold <= 0) {
                continue;
            }

            $value = $breathing[$key] ?? 0.0;
            $scaled = $key === 'lcf' ? (int) round($value * 10.0) : (int) round($value);

            if ($scaled > $threshold) {
                foreach (FluentRuleChecker::whenExceeded($rule)->file($file)->forValue($scaled)->limit($threshold)->result() as $v) {
                    $list[] = $v;
                }
            }
        }

        return $list;
    }
}
