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
 * Finds used stateless operation classes interpreting or changing the same local owner.
 */
#[Rule(
    label: 'Fragmented owner behavior:',
    advice: 'Keep operations with the owner of their state; a concern can organize related behavior without new operation classes.',
)]
final class FragmentedOwnerBehavior extends Check
{
    /**
     * Require multiple independently declared operation classes before suggesting consolidation.
     */
    public function __construct(
        /**
         * Minimum used operation classes over one owner.
         */
        private readonly int $minClasses = 3,
    ) {
        throw_if($minClasses < 2, InvalidArgumentException::class, 'minClasses must be at least 2.');
    }

    /**
     * Identify the optional fragmented owner behavior check.
     */
    public function id(): string
    {
        return 'fragmented_owner_behavior';
    }

    /**
     * Request local operation contracts and actual invocation evidence.
     */
    public function requiredAnalyses(): array
    {
        return ['ownerDesign'];
    }

    /**
     * Suggest owner methods for substantive operations rather than transparent forwarding.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = DesignEvidence::types($project->files);
        $used = [];
        foreach ($project->files as $metrics) {
            $design = $metrics['ownerDesign'] ?? [];
            foreach ($design['invocations'] ?? [] as $call) {
                $used[$call['type'].':'.$call['method'].':'.$call['owner']] = true;
            }
        }

        $groups = [];
        foreach ($types as $type) {
            $operations = $this->operations($type, $types, $used);
            foreach ($operations as $owner => $rows) {
                $classes = $groups[$owner] ?? [];
                $classes[$type['name']] = $rows;
                $groups[$owner] = $classes;
            }
        }

        foreach ($groups as $owner => $classes) {
            if (count($classes) < $this->minClasses) {
                continue;
            }

            $rows = array_merge(...array_values($classes));
            $origin = $rows[0];
            $model = $types[$owner];
            yield new Violation(
                rule: $this->id(), file: $origin['file'], value: count($classes), threshold: $this->minClasses - 1,
                line: $origin['line'],
                message: 'Used stateless operations over '.$model['name'].' are spread across '.count($classes).' classes: '.DesignEvidence::locations($rows).'.'
                        .' Consider methods on '.$model['name'].' and a concern (trait) to organize the behavior. Keep separate policies when they have an independent contract or context.',
            );
        }
    }

    /**
     * Exclude classes with state, composition, additional context, or unknown member accesses.
     */
    private function operations(array $type, array $types, array $used): array
    {
        if (! $type['eligible'] || ! $type['constructorEmpty'] || $type['properties'] !== [] || $type['methods'] === []) {
            return [];
        }

        $rows = [];
        foreach ($type['methods'] as $method) {
            if (! $this->isOwnerOperation($method)) {
                return [];
            }

            $parameters = $method['params'];
            $parameter = array_key_first($parameters);
            $owner = $parameters[$parameter];
            $model = $types[$owner] ?? null;
            $accesses = $method['foreign'];
            if (! $this->isKnownOwnerReceiver($model, $accesses, $parameter)) {
                return [];
            }

            $callIdentity = strtolower($type['name']).':'.strtolower($method['name']).':'.$owner;
            if (! isset($used[$callIdentity])) {
                continue;
            }

            $operations = $rows[$owner] ?? [];
            $operations[] = ['name' => $type['name'].'::'.$method['name'].'()', 'file' => $type['file'], 'line' => $method['line']];
            $rows[$owner] = $operations;
        }

        return $rows;
    }

    /**
     * Require all foreign accesses to target one known local owner.
     */
    private function isKnownOwnerReceiver(?array $model, array $accesses, string $parameter): bool
    {
        $receivers = array_keys($accesses);
        if ($model === null || ! $model['eligible'] || $receivers !== [$parameter]) {
            return false;
        }

        return $this->hasKnownAccesses($model, $accesses[$parameter]);
    }

    /**
     * Require a substantive operation on one owner without additional context or local delegation.
     */
    private function isOwnerOperation(array $method): bool
    {
        $publicInstance = $method['public'] && ! $method['static'] && ! $method['unknown'];
        $parameterTypes = $method['params'];
        $singleOwner = $method['parameterCount'] === 1 && count($parameterTypes) === 1;
        $substantive = $method['meaningful'] || $method['foreignWrites'];

        return $publicInstance && $singleOwner && $substantive && $method['calls'] === [];
    }

    /**
     * Confirm the target members exist and are public on the known owner.
     */
    private function hasKnownAccesses(array $model, array $accesses): bool
    {
        foreach ($accesses as [$kind, $name]) {
            $properties = $model['properties'];
            $methods = $model['methods'];
            $member = $methods[strtolower($name)] ?? [];
            $known = $kind === 'property' ? ($properties[$name] ?? false) : ($member['public'] ?? false);
            if (! $known) {
                return false;
            }
        }

        return true;
    }
}
