<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Singleton access:',
    advice: 'Construct collaborators at the composition root and inject their explicit lifetime.',
)]
final class NoSingletonAccess extends Check
{
    /**
     * Require a resolved declaration returning an object cached in shared storage.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = $this->types($project);
        foreach ($project->files as $path => $facts) {
            foreach ($facts['staticAccessors'] ?? [] as $call) {
                $type = $types[$call['class'] ?? ''] ?? [];
                $method = ($type['methods'] ?? [])[$call['method']] ?? [];
                if (! ($method['sharedReturn'] ?? false) || ($type['unknown'] ?? []) !== []) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $path, value: 1, threshold: 0,
                    line: $call['line'],
                    message: 'Singleton access hides construction and lifetime. Inject the collaborator explicitly.',
                );
            }
        }
    }

    /**
     * Duplicate declarations cannot donate an arbitrary implementation based on file order.
     */
    private function types(ProjectFacts $project): array
    {
        $types = [];
        $duplicates = [];
        foreach ($project->files as $facts) {
            foreach ($facts['classBehavior'] ?? [] as $name => $type) {
                if (isset($types[$name])) {
                    $duplicates[$name] = true;
                }

                $types[$name] = $type;
            }
        }

        return (new BehaviorComposition)->resolve(array_diff_key($types, $duplicates));
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_singleton_access';
    }

    /**
     * Calls and method bodies remain scalar facts until inheritance and traits are composed.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return ['classBehavior'];
    }
}
