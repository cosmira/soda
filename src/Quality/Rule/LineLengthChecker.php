<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Limits;
use Bunnivo\Soda\Quality\Report\Violation;
use Bunnivo\Soda\Quality\Report\ViolationBuilder;
use Illuminate\Support\Collection;

final class LineLengthChecker implements RuleChecker
{
    private const string RULE = 'max_line_length';

    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        if (! $context->config->isRuleEnabled(self::RULE)) {
            return collect();
        }

        $threshold = $context->config->getRule(self::RULE);
        $violations = [];

        foreach (array_keys($context->fileMetrics->qualityMetrics()) as $file) {
            array_push($violations, ...$this->violationsForFile($file, $threshold));
        }

        return collect($violations);
    }

    /**
     * @return list<Violation>
     */
    private function violationsForFile(string $file, int|float $threshold): array
    {
        $contents = @file($file, FILE_IGNORE_NEW_LINES);

        if ($contents === false) {
            return [];
        }

        $violations = [];
        $max = (int) $threshold;

        foreach ($contents as $lineNumber => $line) {
            $length = strlen(rtrim($line, "\r"));

            if ($length <= $max) {
                continue;
            }

            $violations[] = ViolationBuilder::of(self::RULE, $file, new Limits($length, $max))
                ->atLine($lineNumber + 1)
                ->build();
        }

        return $violations;
    }
}
