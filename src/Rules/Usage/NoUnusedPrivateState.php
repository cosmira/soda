<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\Composition\BehaviorComposition;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * A private declaration or a write does not establish a useful state responsibility.
 */
final class NoUnusedPrivateState extends Check
{
    /**
     * Report private fields and constants without reads in the composed class.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = [];
        $methodReads = [];
        $resolved = (new BehaviorComposition)->project($project);
        foreach ($project->files as $file => $facts) {
            foreach ($facts['privateState'] ?? [] as $key => $type) {
                $types[$key] = $type + ['file' => $file];
                $methodReads += $type['methodReads'];
            }
        }

        foreach ($types as $key => $type) {
            if ($type['trait']) {
                continue;
            }

            $surface = $this->surface($key, $types);
            $class = $resolved[$key] ?? ['methods' => []];
            $reads = $surface['reads'] + $this->selectedReads($class['methods'], $key, $methodReads);
            foreach ($surface['declarations'] as $member => $declaration) {
                [$kind, $name] = explode(':', $member, 2);
                $dynamic = $kind.':*';
                if (isset($reads[$member]) || isset($reads[$dynamic])) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $declaration['file'], value: 1, threshold: 0,
                    class: $type['name'], line: $declaration['line'],
                    message: sprintf('Private %s %s is never read by %s, including its traits. Remove the unused state.', $kind, $name, $type['name']),
                );
            }
        }
    }

    /**
     * Compose only trait state: a parent's private members remain owned by the parent.
     */
    private function surface(string $key, array $types, array $seen = []): array
    {
        $surface = ['declarations' => [], 'reads' => []];
        if (isset($seen[$key]) || ! isset($types[$key])) {
            return $surface;
        }

        $seen[$key] = true;
        $type = $types[$key];
        $surface['reads'] = $type['reads'];
        $declarations = [];
        foreach ($type['declarations'] as $member => $line) {
            $declarations[$member] = ['line' => $line, 'file' => $type['file']];
        }

        $surface['declarations'] = $declarations;
        foreach ($type['traits'] as $trait) {
            $composed = $this->surface($trait, $types, $seen);
            $surface['declarations'] += $composed['declarations'];
            $surface['reads'] += $composed['reads'];
        }

        return $surface;
    }

    /**
     * Only the selected bodies in the field owner's lexical scope establish reads of its private slots.
     */
    private function selectedReads(array $methods, string $owner, array $methodReads): array
    {
        $reads = [];
        foreach ($methods as $method) {
            if ($method['scope'] === $owner) {
                $reads += $methodReads[$method['origin']] ?? [];
            }
        }

        return $reads;
    }

    /**
     * Identify this check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_unused_private_state';
    }

    /**
     * Declare the fact collectors required by this check.
     */
    public function requiredAnalyses(): array
    {
        return ['privateState', 'classBehavior'];
    }
}
