<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Complexity;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

/**
 * Optionally limits consecutive first local assignments in direct callable bodies.
 */
#[Rule(
    label: 'Consecutive initial local assignments:',
    advice: 'Review long local preparation sequences and name a meaningful calculation when it clarifies the scenario.',
    default: 8,
)]
final class MaxConsecutiveLocalAssignments extends Check
{
    /**
     * Set the inclusive maximum run length; zero rejects any initial local binding.
     */
    public function __construct(
        /**
         * Maximum accepted number of consecutive initial local assignments.
         */
        private readonly int $limit = 8,
    ) {
        throw_if($limit < 0, InvalidArgumentException::class, 'Consecutive assignment limit must be non-negative.');
    }

    /**
     * Identify the local preparation metric.
     */
    public function id(): string
    {
        return 'max_consecutive_local_assignments';
    }

    /**
     * Measure direct callable bodies without requesting other metric collectors.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report each maximal excessive run once at its first assignment.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $analyzer = new ConsecutiveAssignmentRuns;
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, FunctionLike::class) as $callable) {
            foreach ($analyzer->collect($callable) as $run) {
                if (count($run) <= $this->limit) {
                    continue;
                }

                $first = $run[0];
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: count($run), threshold: $this->limit,
                    line: $first['line'],
                    message: 'Long local preparation sequence: $'.implode(', $', array_column($run, 'name')).'. Review whether a named calculation clarifies this step; do not introduce wrappers solely to lower the count.',
                );
            }
        }
    }
}
