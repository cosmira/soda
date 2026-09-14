<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;

abstract class Check
{
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
     * Return required collector groups, or null when all facts may be needed.
     *
     * @return list<string>|null
     */
    public function requiredAnalyses(): ?array
    {
        return null;
    }
}
