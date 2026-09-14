<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class MaxClassesPerProject extends Check
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     */
    public function __construct(private readonly int $limit) {}

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_classes_per_project';
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkProject(ProjectFacts $project): iterable
    {
        if ($this->limit <= 0) {
            return;
        }

        $total = array_sum(array_column($project->files, 'classes_count'));
        if ($total > $this->limit) {
            yield new Violation($this->id(), array_key_first($project->files) ?? '.', $total, $this->limit);
        }
    }
}
