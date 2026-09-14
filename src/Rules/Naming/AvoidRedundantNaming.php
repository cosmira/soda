<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class AvoidRedundantNaming extends Check
{
    /**
     * Set the similarity percentage needed for a finding.
     */
    public function __construct(private readonly int $threshold = 80) {}

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'avoid_redundant_naming';
    }

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return ['naming'];
    }

    /**
     * Compare names against the context already expressed by their owners.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        if ($this->threshold <= 0) {
            return;
        }

        $threshold = $this->normalisedSimilarityThreshold($this->threshold);
        $analyser = new RedundantNamingAnalyser($threshold);
        $naming = $file->metrics['naming'] ?? [];
        $hasNames = isset($naming['classes'], $naming['methods']);
        if (! $hasNames) {
            return;
        }

        foreach ($analyser->analyse($naming) as $finding) {
            yield $this->toViolation($file->path, $finding, (int) $threshold);
        }
    }

    /**
     * Convert the configured similarity percentage into a fractional cutoff.
     */
    private function normalisedSimilarityThreshold(int $threshold): float
    {
        $isPercentage = $threshold >= 1 && $threshold <= 100;

        return $isPercentage ? (float) $threshold : 80.0;
    }

    /**
     * @param array{type: string, current: string, suggested: string, reason: string, similarity: float, line: int, className?: string} $finding
     */
    private function toViolation(string $file, array $finding, int $threshold): Violation
    {
        $similarity = (int) round($finding['similarity']);
        $isMethod = $finding['type'] === 'method' && isset($finding['className']);
        $class = $finding['type'] === 'class' ? $finding['current'] : null;
        if ($isMethod) {
            $class = $finding['className'];
        }

        $method = $isMethod ? strstr($finding['current'].'(', '(', true) : null;

        return new Violation(
            rule: $this->id(), file: $file, value: $similarity, threshold: $threshold, method: $method, class: $class, line: $finding['line'],
            message: sprintf('Redundant naming: %s (%d%%)', $this->compactMessage($finding['current'], $finding['suggested']), $similarity),
        );
    }

    /**
     * Remove repeated rule text from a diagnostic displayed beside its heading.
     */
    private function compactMessage(string $current, string $suggested): string
    {
        $cur = str_contains($current, '(') ? substr($current, 0, (int) strpos($current, '(')) : $current;
        $sug = str_contains($suggested, '(') ? substr($suggested, 0, (int) strpos($suggested, '(')) : $suggested;

        return $cur.' → '.$sug;
    }
}
