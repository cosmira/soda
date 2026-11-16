<?php

declare(strict_types=1);

namespace Cosmira\Soda\Contracts;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Support\FnmatchPathExclusion;

abstract class Check
{
    /**
     * File paths and masks exempt from this configured rule instance.
     *
     * @var list<string>
     */
    private array $excludedPaths = [];

    /**
     * Match configured path boundaries without changing other rule instances.
     */
    private ?FnmatchPathExclusion $exclusion = null;

    /**
     * Exempt matching files from this rule, retaining their facts for other rules.
     *
     * @param list<non-empty-string> $paths File paths, directory boundaries, or filename masks.
     */
    final public function exclude(array $paths): static
    {
        foreach ($paths as $path) {
            throw_if(
                ! is_string($path) || $path === '',
                \InvalidArgumentException::class,
                'Each rule exclusion must be a non-empty path.',
            );
        }

        $this->excludedPaths = array_values(array_unique([...$this->excludedPaths, ...$paths]));
        $this->exclusion = new FnmatchPathExclusion(
            $this->excludedPaths,
            $this->excludedPaths,
            flags: 0,
        );

        return $this;
    }

    /**
     * Determine whether a source path is exempt from this configured rule.
     */
    final public function isExcludedPath(string $path): bool
    {
        return $this->exclusion?->isExcludedPath($path) ?? false;
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    abstract public function id(): string;

    /**
     * Check one parsed file while its source and AST are available.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        return [];
    }

    /**
     * Check the complete project using facts that do not retain PHP ASTs.
     *
     * @return iterable<Violation>
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        return [];
    }

    /**
     * Request additional collector groups; syntax and structural facts are always available.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }
}
