<?php

declare(strict_types=1);

namespace Cosmira\Soda\Research;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\ExpressionCheck;
use InvalidArgumentException;

/**
 * Rejected threshold candidate retained only to reproduce the corpus experiment.
 */
final class MaxDisconnectedMethodGroups extends ExpressionCheck
{
    /**
     * Restrict the candidate to a meaningful sample size; neither parameter changes graph facts.
     */
    public function __construct(private readonly int $limit = 1, private readonly int $minimumMethods = 4)
    {
        throw_if($limit < 0, InvalidArgumentException::class, 'Method group limit must be non-negative.');
        throw_if($minimumMethods < 2, InvalidArgumentException::class, 'Cohesion requires at least two methods.');
    }

    /**
     * Identify this optional candidate independently of established size rules.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_disconnected_method_groups';
    }

    /**
     * Unknown relationships disqualify a violation instead of becoming absent graph edges.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('class where method_groups > %d and cohesion_methods >= %d and cohesion_unknown == 0', $this->limit, $this->minimumMethods);
    }

    /**
     * Include separately collected traits/enums/anonymous types without altering legacy scopes.
     *
     * @return iterable<array<string, mixed>>
     */
    #[\Override]
    protected function rows(FileFacts $file): iterable
    {
        foreach ($file->metrics['cohesion'] ?? [] as $name => $type) {
            $graph = $type['composed'];
            yield ['name' => $name, 'line' => $type['line'], ...$graph['metrics']];
        }
    }

    /**
     * Name the actual groups and state why they still need a design judgment.
     *
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        $types = $file->metrics['cohesion'];
        $type = $types[$row['name']];
        $graph = $type['composed'];
        $groups = array_map(fn (array $methods): string => '['.implode(', ', $methods).']', $graph['groups']);

        return new Violation(
            rule: $this->id(), file: $file->path, value: $row['method_groups'], threshold: $this->limit,
            class: $row['name'], line: $row['line'],
            message: 'Observed method groups: '.implode(' ', $groups).'. This is structural connectivity, not a count of responsibilities; keep useful concerns and value operations together.',
        );
    }
}
