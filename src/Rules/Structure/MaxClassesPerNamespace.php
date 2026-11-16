<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Classes per namespace:',
    advice: 'Split the namespace into cohesive domain capabilities with names that explain their responsibility.',
    default: 15,
)]
final class MaxClassesPerNamespace extends Check
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
        return 'max_classes_per_namespace';
    }

    /**
     * Report namespaces whose collected class count exceeds the configured limit.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkProject(ProjectFacts $project): iterable
    {
        if ($this->limit <= 0) {
            return;
        }

        foreach ($project->namespaces() as $namespace => $row) {
            if ($row['count'] > $this->limit) {
                yield new Violation($this->id(), $row['file'], $row['count'], $this->limit, class: $namespace);
            }
        }
    }
}
