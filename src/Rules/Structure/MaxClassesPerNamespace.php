<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class MaxClassesPerNamespace extends Check
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
        return 'max_classes_per_namespace';
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

        foreach ($project->namespaces() as $namespace => $row) {
            if ($row['count'] > $this->limit) {
                yield new Violation($this->id(), $row['file'], $row['count'], $this->limit, class: $namespace);
            }
        }
    }
}
