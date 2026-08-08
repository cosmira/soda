<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Report\Violation;
use Bunnivo\Soda\Quality\RuleReadability\BreathingThresholdViolationCollector;
use Illuminate\Support\Collection;

final class BreathingChecker implements RuleChecker
{
    private const array MIN_RULES = [
        'min_code_breathing_score'         => 'cbs',
        'min_visual_breathing_index'       => 'vbi',
        'min_identifier_readability_score' => 'irs',
        'min_code_oxygen_level'            => 'col',
    ];

    private const array MAX_RULES = [
        'max_weighted_cognitive_density' => 'wcd',
        'max_logical_complexity_factor'  => 'lcf',
    ];

    /**
     * @return array<string, string>
     */
    public static function minRuleMap(): array
    {
        return self::MIN_RULES;
    }

    /**
     * @return array<string, string>
     */
    public static function maxRuleMap(): array
    {
        return self::MAX_RULES;
    }

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        /** @var list<Violation> $list */
        $list = [];

        foreach ($context->fileMetrics->qualityMetrics() as $file => $data) {
            $breathing = $data['breathing'] ?? null;
            if ($breathing === null) {
                continue;
            }

            $list = array_merge($list, BreathingThresholdViolationCollector::collectBelowThresholds($context, $file, $breathing));
            $list = array_merge($list, BreathingThresholdViolationCollector::collectAboveThresholds($context, $file, $breathing));
        }

        return collect($list)->values();
    }
}
