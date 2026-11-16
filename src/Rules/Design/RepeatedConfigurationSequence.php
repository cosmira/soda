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
 * Finds repeated fresh-object setup and suggests a scenario-oriented fluent API.
 */
#[Rule(
    label: 'Repeated object configuration:',
    advice: 'Consider a fluent object or named construction for repeated setup; keep execution and evaluation order explicit.',
)]
final class RepeatedConfigurationSequence extends Check
{
    /**
     * Require repeated scenarios with several proved state-only configuration steps.
     */
    public function __construct(
        /**
         * Minimum consumers repeating a setup scenario.
         */
        private readonly int $minOccurrences = 3,
        /**
         * Minimum state-only setup calls before execution.
         */
        private readonly int $minConfigurationCalls = 2,
    ) {
        throw_if($minOccurrences < 2 || $minConfigurationCalls < 2, InvalidArgumentException::class, 'Both thresholds must be at least 2.');
    }

    /**
     * Identify the optional fluent API review signal.
     */
    public function id(): string
    {
        return 'repeated_configuration_sequence';
    }

    /**
     * Request fresh-object setup sequences and declared method bodies.
     */
    public function requiredAnalyses(): array
    {
        return ['ownerDesign'];
    }

    /**
     * Group complete setup scenarios ending in execution or returning the configured object.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = DesignEvidence::types($project->files);
        $groups = [];
        foreach ($project->files as $file => $metrics) {
            $design = $metrics['ownerDesign'] ?? [];
            foreach ($design['sequences'] ?? [] as $sequence) {
                $type = $types[$sequence['type']] ?? null;
                $steps = $type === null ? null : $this->steps($type, $sequence['calls'], $sequence['returnsObject']);
                if ($steps === null) {
                    continue;
                }

                $key = $sequence['type'].':'.implode(':', $steps);
                $scenarios = $groups[$key] ?? [];
                $scenarios[$file.':'.$sequence['name']] ??= [...$sequence, 'file' => $file, 'owner' => $type['name'], 'steps' => $steps];
                $groups[$key] = $scenarios;
            }
        }

        foreach ($groups as $scenarios) {
            if (count($scenarios) < $this->minOccurrences) {
                continue;
            }

            $rows = array_values($scenarios);
            $first = $rows[0];
            yield new Violation(
                rule: $this->id(), file: $first['file'], value: count($rows), threshold: $this->minOccurrences - 1,
                line: $first['line'],
                message: 'Fresh '.$first['owner'].' setup repeats '.implode(' -> ', $first['steps']).' in '.count($rows).' consumers: '.DesignEvidence::locations($rows).'.'
                        .' Consider a fluent object or named construction that expresses this scenario.'
                        .($first['returnsObject'] ? ' Keep construction on the owner instead of multiplying setup-only factory classes.' : '')
                        .' Preserve argument evaluation order and make the execution boundary explicit.',
            );
        }
    }

    /**
     * Require state-only setup ending in a known operation or the configured object itself.
     */
    private function steps(array $type, array $calls, bool $returnsObject): ?array
    {
        $short = count($calls) < $this->minConfigurationCalls + ($returnsObject ? 0 : 1);
        if (! $type['eligible'] || $short) {
            return null;
        }

        $methods = $type['methods'];
        $steps = [];
        $last = count($calls) - 1;
        foreach ($calls as $index => $call) {
            $method = $methods[$call['method']] ?? null;
            $terminal = ! $returnsObject && $index === $last;
            if (! $this->isScenarioStep($method, $call, $terminal)) {
                return null;
            }

            $steps[] = $method['name'].'()';
        }

        return $returnsObject ? [...$steps, 'return object'] : $steps;
    }

    /**
     * Distinguish proved state-only setup from the final public execution operation.
     */
    private function isScenarioStep(?array $method, array $call, bool $last): bool
    {
        if ($method === null || ! $method['public'] || $method['static']) {
            return false;
        }

        if ($call['arguments'] !== $method['parameterCount']) {
            return false;
        }

        return $last
            ? ! $method['configuration'] && $method['getter'] === null
            : $method['configuration'] && ! $method['unknown'];
    }
}
