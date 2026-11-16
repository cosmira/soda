<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

/**
 * Implements clauses alone do not constitute a consumer of a local abstraction.
 */
#[Rule(
    label: 'Unconsumed local interfaces:',
    advice: 'List externally consumed interfaces in contracts. Remove only unused interfaces; an implements clause alone is not a type consumer for this policy.',
    severity: 'error',
)]
final class NoRedundantLocalInterfaces extends Check
{
    /**
     * Register interfaces whose consumers reside outside the analyzed source.
     *
     * @param list<string> $contracts Qualified interfaces consumed outside analyzed source.
     */
    public function __construct(
        /**
         * Interface names with explicitly declared external consumers.
         */
        private readonly array $contracts = [],
    ) {}

    /**
     * Require a type consumer beyond an implements clause or an explicit external contract.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $declarations = [];
        $references = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        $dependencies = [];
        foreach ($project->files as $file => $facts) {
            $usage = $facts['interfaceUsage'] ?? [];
            $references += $usage['references'] ?? [];
            $dependencies = array_replace_recursive($dependencies, $usage['dependencies'] ?? []);
            foreach ($usage['declarations'] ?? [] as $key => $declaration) {
                $declarations[$key] = $declaration + ['file' => $file];
            }
        }

        $references = $this->reachable($references, $dependencies);
        foreach ($declarations as $key => $declaration) {
            if (isset($references[$key])) {
                continue;
            }

            yield new Violation(
                rule: $this->id(), file: $declaration['file'], value: 1, threshold: 0,
                class: $declaration['name'], line: $declaration['line'],
                message: $declaration['name'].' has no explicit type consumer in the analyzed project. Declare external consumers in contracts; remove it only if it has no consumers.',
            );
        }
    }

    /**
     * Follow signature and inheritance dependencies from actual consumers, terminating on cycles.
     */
    private function reachable(array $references, array $dependencies): array
    {
        $pending = array_keys($references);
        while ($pending !== []) {
            $owner = array_pop($pending);
            foreach ($dependencies[$owner] ?? [] as $target => $used) {
                if (isset($references[$target])) {
                    continue;
                }

                $references[$target] = $used;
                $pending[] = $target;
            }
        }

        return $references;
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_redundant_local_interfaces';
    }

    /**
     * Declare the fact collectors required by this check.
     */
    public function requiredAnalyses(): array
    {
        return ['interfaceUsage'];
    }
}
