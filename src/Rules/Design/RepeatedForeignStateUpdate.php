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
 * Finds copied arithmetic updates of one owner's numeric state in independent consumers.
 */
#[Rule(
    label: 'Repeated foreign state updates:',
    advice: 'Give repeated arithmetic over owner state a named owner operation; preserve deliberate record APIs and do not infer atomicity.',
)]
final class RepeatedForeignStateUpdate extends Check
{
    /**
     * Require independent consumers before suggesting a named owner operation.
     */
    public function __construct(
        /**
         * Minimum consumers repeating the same state update.
         */
        private readonly int $minConsumers = 3,
    ) {
        throw_if($minConsumers < 2, InvalidArgumentException::class, 'minConsumers must be at least 2.');
    }

    /**
     * Identify the optional external-update review signal.
     */
    public function id(): string
    {
        return 'repeated_foreign_state_update';
    }

    /**
     * Reuse scalar owner declarations and arithmetic call-site evidence.
     */
    public function requiredAnalyses(): array
    {
        return ['ownerDesign'];
    }

    /**
     * Resolve getter/setter field identity before grouping distinct consumers.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = DesignEvidence::types($project->files);
        $groups = [];
        foreach ($project->files as $file => $metrics) {
            $design = $metrics['ownerDesign'] ?? [];
            foreach ($design['updates'] ?? [] as $update) {
                $owner = $types[$update['type']] ?? null;
                $field = $owner === null ? null : $this->updatedField($owner, $update);
                if ($field === null) {
                    continue;
                }

                $key = $update['type'].':'.$field.':'.$update['key'];
                $identity = $file.':'.$update['name'];
                $consumers = $groups[$key] ?? [];
                $consumers[$identity] ??= [...$update, 'file' => $file, 'owner' => $owner['name'], 'field' => $field];
                $groups[$key] = $consumers;
            }
        }

        foreach ($groups as $consumers) {
            if (count($consumers) < $this->minConsumers) {
                continue;
            }

            $rows = array_values($consumers);
            $first = $rows[0];
            yield new Violation(
                rule: $this->id(), file: $first['file'], value: count($rows), threshold: $this->minConsumers - 1,
                line: $first['line'],
                message: 'The same arithmetic update of '.$first['owner'].'::$'.$first['field'].' is computed outside its owner in '.count($rows)
                    .' consumers: '.DesignEvidence::locations($rows).'. Expression: '.$first['expression'].'.'
                    .' If these express one domain operation, introduce a named method on '.$first['owner'].' (for example, debit($amount) for a balance subtraction).'
                    .' A concern can organize related owner behavior. Transport records and generic numeric structures may intentionally expose this API; review the contract. No atomicity is inferred.',
            );
        }
    }

    /**
     * Prove that all reads and the sole write target one mutable numeric field.
     */
    private function updatedField(array $owner, array $update): ?string
    {
        $methods = $owner['methods'];
        $setter = $methods[$update['setter']] ?? null;
        $contract = $setter['setter'] ?? null;
        if (! $owner['eligible'] || $contract === null) {
            return null;
        }

        $field = $contract['field'];
        $fields = $owner['numericFields'];
        $argumentMatches = $update['argument'] === null || $update['argument'] === $contract['parameter'];
        $final = $owner['final'] || $setter['final'];
        if (! isset($fields[$field]) || ! $argumentMatches || ! $final) {
            return null;
        }

        foreach ($update['getters'] as $name) {
            if (! $this->isMatchingUpdateGetter($methods[$name] ?? null, $field, $owner['final'])) {
                return null;
            }
        }

        return $field;
    }

    /**
     * Resolve the getter to the written field and exclude possible runtime overrides.
     */
    private function isMatchingUpdateGetter(?array $getter, string $field, bool $finalOwner): bool
    {
        if ($getter === null) {
            return false;
        }

        $final = $finalOwner || $getter['final'];

        return $getter['getter'] === $field && $final;
    }
}
