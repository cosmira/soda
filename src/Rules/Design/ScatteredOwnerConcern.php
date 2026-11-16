<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Design;

use Cosmira\Soda\Analysis\Design\DesignEvidence;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;

/**
 * Reports interleaved substantive owner behavior, not a quota of responsibilities.
 */
#[Rule(
    label: 'Scattered owner concerns:',
    advice: 'Group related methods beside their state; consider a concern when a separate file improves navigation.',
)]
final class ScatteredOwnerConcern extends Check
{
    use OwnerGroups;

    /**
     * Require a stateful method group large enough to have a useful navigation cost.
     */
    public function __construct(
        /**
         * Minimum substantive methods in an owner group.
         */
        private readonly int $minMethods = 3,
    ) {
        throw_if($minMethods < 3, InvalidArgumentException::class, 'minMethods must be at least 3.');
    }

    /**
     * Identify the optional concern organization rule.
     */
    public function id(): string
    {
        return 'scattered_owner_concern';
    }

    /**
     * Request local state and call relationships with declaration positions.
     */
    public function requiredAnalyses(): array
    {
        return ['ownerDesign'];
    }

    /**
     * Suggest grouping beside the owner only when unrelated behavior interrupts the group.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        foreach (DesignEvidence::types($project->files) as $type) {
            foreach ($this->groups($type) as $members) {
                if (! $this->isConcern($members, $this->minMethods)) {
                    continue;
                }

                $orders = array_column($members, 'order');
                $span = max($orders) - min($orders) + 1;
                if ($span === count($members)) {
                    continue;
                }

                $evidence = $this->concernEvidence($type, $members);
                $rows = $evidence['rows'];
                yield new Violation(
                    rule: $this->id(), file: $type['file'], value: count($members), threshold: $this->minMethods - 1,
                    class: $type['name'], line: min(array_column($rows, 'line')),
                    message: 'A stateful group is interleaved with unrelated methods: '.DesignEvidence::locations($rows).'.'
                        .' Shared owner fields: '.implode(', ', $evidence['fields']).'.'
                        .' Group these methods together; consider a concern (trait) if a separate file improves navigation.'
                        .' Keep the behavior on '.$type['name'].'.',
                );
            }
        }
    }
}
