<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Config\RuleExpression;
use Cosmira\Soda\Reporting\Violation;

abstract class ExpressionCheck extends Check
{
    /**
     * The compiled predicate belongs to this configured rule instance.
     */
    private ?RuleExpression $compiled = null;

    /**
     * Keep the rule's condition visible in its PHP implementation.
     */
    abstract protected function expression(): string;

    /**
     * Build a diagnostic with the actual measurement and configured threshold.
     *
     * @param array<string, mixed> $row
     */
    abstract protected function violation(FileFacts $file, array $row): Violation;

    /**
     * Allow threshold rules to preserve their documented disabled boundary values.
     */
    protected function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return $this->compiled()->requiredAnalyses();
    }

    /**
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $isClass = $this->compiled()->scope === 'class';
        if ($isClass) {
            return [];
        }

        return $this->matchingRows($file);
    }

    /**
     * Class checks use the complete inherited surface after project aggregation.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkProject(ProjectFacts $project): iterable
    {
        $isClass = $this->compiled()->scope === 'class';
        if (! $isClass) {
            return;
        }

        foreach ($project->files as $path => $metrics) {
            yield from $this->matchingRows(new FileFacts($path, '', [], $metrics));
        }
    }

    /**
     * Compile once and attach the rule identifier to configuration errors.
     */
    private function compiled(): RuleExpression
    {
        try {
            return $this->compiled ??= new RuleExpression($this->expression());
        } catch (ConfigException $configException) {
            throw new ConfigException("Rule '".$this->id()."': ".$configException->getMessage(), 0, $configException);
        }
    }

    /**
     * @return iterable<Violation>
     */
    private function matchingRows(FileFacts $file): iterable
    {
        $isEnabled = $this->isEnabled();
        if (! $isEnabled) {
            return;
        }

        foreach ($file->rows($this->compiled()->scope) as $row) {
            $isMatch = $this->compiled()->isMatch($row);
            if ($isMatch) {
                yield $this->violation($file, $row);
            }
        }
    }
}
