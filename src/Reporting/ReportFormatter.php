<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use function basename;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;

use function sprintf;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use Symfony\Component\Console\Formatter\OutputFormatter;

final readonly class ReportFormatter
{
    /**
     * Defines separator width used by this policy.
     */
    private const int SEPARATOR_WIDTH = 60;

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private RuleMetadata $ruleMetadata,
    ) {}

    /**
     * Write using the current analysis state.
     */
    public function write(OutputStyle $output, QualityResult $result, string $projectRoot): void
    {
        $output->writeln([
            '<fg=bright-blue;options=bold>Soda Quality</>',
            '<fg=gray>'.str_repeat('-', self::SEPARATOR_WIDTH).'</>',
        ]);
        $output->newLine();

        $n = $result->violations->count();
        if ($n > 0) {
            $output->writeln(sprintf(
                '<fg=default>%s</>',
                OutputFormatter::escape($n === 1 ? '1 issue' : $n.' issues'),
            ));
            $output->newLine();

            $this->writeViolationsGrouped($output, $result->violations, $projectRoot);
            $output->writeln('<fg=gray>'.str_repeat('-', self::SEPARATOR_WIDTH).'</>');
            $output->newLine();
        }

        $passed = $result->isPassing();
        if ($passed) {
            $output->writeln('<fg=green;options=bold>[OK]</> No issues');

            return;
        }

        $output->writeln(sprintf(
            '<fg=red;options=bold>[FAIL]</> %s',
            $n === 1 ? '1 issue' : $n.' issues',
        ));
    }

    /**
     * @param Collection<int, Violation> $violations
     */
    private function writeViolationsGrouped(OutputStyle $output, Collection $violations, string $projectRoot): void
    {
        /** @var Collection<string, Collection<int, Violation>> $byFile */
        $byFile = $violations->groupBy(fn (Violation $v) => $v->file)->sortKeys();

        foreach ($byFile as $file => $list) {
            $rel = $this->shortenPath($file, $projectRoot);
            $output->writeln('<fg=cyan;options=bold>'.OutputFormatter::escape($rel).'</>');

            foreach ($list as $v) {
                $this->writeViolationDetail($output, $v);
            }

            $output->newLine();
        }
    }

    /**
     * Format the measured value and the limit it violates.
     */
    private function formatThresholdDetail(Violation $v): string
    {
        $label = OutputFormatter::escape($this->ruleMetadata->label($v->rule));
        $lim = ['value' => $v->value, 'threshold' => $v->threshold];
        $valueStr = OutputFormatter::escape($this->formatLimitValue($v->rule, $lim['value'], $lim['threshold']));

        return sprintf('<fg=default>%s</> %s', $label, $valueStr);
    }

    /**
     * Make an absolute diagnostic path relative to the displayed project root.
     */
    private function shortenPath(string $path, string $root): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);
        $hasRoot = $root !== '';
        $isInsideRoot = str_starts_with($path, $root.'/') || $path === $root;

        if ($hasRoot && $isInsideRoot) {
            $cut = substr($path, strlen($root) + 1);
            $hasExtension = $cut !== '' && $cut !== false;

            return $hasExtension ? $cut : basename($path);
        }

        return $path;
    }

    /**
     * Write violation detail using the current analysis state.
     */
    private function writeViolationDetail(OutputStyle $output, Violation $v): void
    {
        $isError = $this->ruleMetadata->severity($v->rule) === RuleMetadata::SEVERITY_ERROR;
        $mark = $isError ? '<fg=red;options=bold>×</>' : '<fg=yellow;options=bold>!</>';
        $hasLine = $v->line !== null;

        $lineCol = $hasLine ? (string) $v->line : '—';
        $scope = $v->method ?? $v->class;
        $locLine = '<options=bold>Line '.$lineCol.'</>';
        if ($scope !== null) {
            $locLine .= ' <fg=gray>('.OutputFormatter::escape($scope).')</>';
        }

        $detail = $v->message !== null
            ? OutputFormatter::escape($v->message)
            : $this->formatThresholdDetail($v);
        $output->writeln(sprintf('  %s %s', $mark, $locLine));
        $output->writeln('    '.$detail);

        $advice = $this->ruleMetadata->advice($v->rule);
        if ($advice !== null) {
            $output->writeln('    <fg=gray>↳ '.OutputFormatter::escape($advice).'</>');
        }
    }

    /**
     * Render a numeric limit without unnecessary fractional digits.
     */
    private function formatLimitValue(string $rule, int $value, int $threshold): string
    {
        $cmp = $this->ruleMetadata->comparison($rule);

        return sprintf('%d (%s %d)', $value, $cmp, $threshold);
    }
}
