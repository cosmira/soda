<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use function array_key_first;
use function arsort;

use Bunnivo\Soda\Quality\Engine\LayerMixingDirectoryAggregator;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\Report\OccurrenceViolationFactory;
use Bunnivo\Soda\Quality\Report\Violation;

use function count;

use Illuminate\Support\Collection;

use function is_numeric;
use function sprintf;

final class LayerMixingChecker implements RuleChecker
{
    public const string RULE = 'max_layer_dominance_percentage';

    public const int DEFAULT_MIN_FILES = 4;

    /**
     * @return Collection<int, Violation>
     */
    #[\Override]
    public function check(EvaluationContext $context): Collection
    {
        if (! $context->config->isRuleEnabled(self::RULE)) {
            return collect();
        }

        $threshold = (int) $context->config->getRule(self::RULE);

        if ($threshold <= 0) {
            return collect();
        }

        $minFiles = $this->minFiles($context);
        $violations = collect();
        $limits = ['threshold' => $threshold, 'minFiles' => $minFiles];

        foreach (LayerMixingDirectoryAggregator::aggregate($context->fileMetrics->qualityMetrics()) as $directory => $summary) {
            $violation = $this->violationForDirectory($directory, $summary, $limits);

            if ($violation instanceof Violation) {
                $violations->push($violation);
            }
        }

        return $violations;
    }

    private function minFiles(EvaluationContext $context): int
    {
        $configured = $context->config->ruleOptions(self::RULE)['min_files'] ?? self::DEFAULT_MIN_FILES;

        return is_numeric($configured) ? max(1, (int) $configured) : self::DEFAULT_MIN_FILES;
    }

    /**
     * @param array{file: string, fileCount: int, typeCounts: array<string, int>} $summary
     * @param array{threshold: int, minFiles: int}                                $limits
     */
    private function violationForDirectory(string $directory, array $summary, array $limits): ?Violation
    {
        if (! $this->shouldReportDirectory($summary, $limits['minFiles'])) {
            return null;
        }

        [$dominantType, $dominantCount] = $this->dominantType($summary['typeCounts']);

        if ($dominantType === LayerMixingDirectoryAggregator::PLAIN_TYPE || $this->isDirectoryNamedAfterType($directory, $dominantType, $summary['typeCounts'])) {
            return null;
        }

        $share = ($dominantCount / $summary['fileCount']) * 100;

        if ($share < $limits['threshold']) {
            return null;
        }

        $otherTypes = $summary['typeCounts'];
        unset($otherTypes[$dominantType]);

        return OccurrenceViolationFactory::build([
            'rule'      => self::RULE,
            'file'      => $summary['file'],
            'value'     => (int) round($share),
            'threshold' => $limits['threshold'],
            'message'   => sprintf(
                'Layer mixing: %s dominates %.1f%% of %d PHP files in %s; other types: %s',
                $dominantType,
                $share,
                $summary['fileCount'],
                $directory,
                $this->otherTypes($otherTypes),
            ),
            'class'     => $directory,
        ]);
    }

    /**
     * @param array{file: string, fileCount: int, typeCounts: array<string, int>} $summary
     */
    private function shouldReportDirectory(array $summary, int $minFiles): bool
    {
        return $summary['fileCount'] >= $minFiles && count($summary['typeCounts']) > 1;
    }

    /**
     * Returns true when the directory appears to be named after the dominant type,
     * meaning dominance is expected rather than problematic mixing.
     *
     * Two cases:
     *  1. Directory name contains the type name → "Fields/" with "Field" dominant.
     *  2. Every non-Plain type ends with the directory's singular concept
     *     → "Commands/" with "GeneratorCommand" + "Command" (all are commands).
     *
     * @param array<string, int> $typeCounts
     */
    private function isDirectoryNamedAfterType(string $directory, string $dominantType, array $typeCounts): bool
    {
        $dirName = strtolower(basename($directory));

        $dominant = strtolower($dominantType);

        if (str_contains($dirName, $dominant) || str_contains($dominant, $dirName)) {
            return true;
        }

        $dirCore = rtrim($dirName, 's');
        $nonPlain = array_keys(array_diff_key($typeCounts, [LayerMixingDirectoryAggregator::PLAIN_TYPE => 0]));

        return array_filter($nonPlain, fn (string $t): bool => ! str_ends_with(strtolower($t), $dirCore)) === [];
    }

    /**
     * @param array<string, int> $typeCounts
     *
     * @return array{0: string, 1: int}
     */
    private function dominantType(array $typeCounts): array
    {
        arsort($typeCounts);
        $type = array_key_first($typeCounts) ?? LayerMixingDirectoryAggregator::PLAIN_TYPE;

        return [$type, $typeCounts[$type] ?? 0];
    }

    /**
     * @param array<string, int> $typeCounts
     */
    private function otherTypes(array $typeCounts): string
    {
        arsort($typeCounts);
        $parts = [];

        foreach ($typeCounts as $type => $count) {
            $parts[] = sprintf('%s=%d', $type, $count);
        }

        return $parts !== [] ? implode(', ', $parts) : 'none';
    }
}
