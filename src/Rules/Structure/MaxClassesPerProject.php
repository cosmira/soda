<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Classes per project:',
    advice: 'Look for fragmented abstractions and merge types that do not own distinct behavior or policy.',
    severity: 'error',
    default: 300,
)]
final class MaxClassesPerProject extends Check
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     *
     * @param int $limit Maximum accepted value of this metric.
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
     * Report a project whose collected class count exceeds the configured limit.
     *
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
