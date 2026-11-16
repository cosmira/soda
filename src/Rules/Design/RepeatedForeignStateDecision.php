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
 * Finds copied questions about a local owner's state in distinct consumers.
 */
#[Rule(
    label: 'Repeated foreign state decisions:',
    advice: 'Give a repeated domain question a name on its owner; a concern can organize related owner behavior.',
)]
final class RepeatedForeignStateDecision extends Check
{
    /**
     * Require several independent consumers before recommending an owner predicate.
     */
    public function __construct(
        /**
         * Minimum independent consumers of a question.
         */
        private readonly int $minConsumers = 3,
    ) {
        throw_if($minConsumers < 2, InvalidArgumentException::class, 'minConsumers must be at least 2.');
    }

    /**
     * Identify the optional external-decision review signal.
     */
    public function id(): string
    {
        return 'repeated_foreign_state_decision';
    }

    /**
     * Collect receiver types and exact compound-expression fingerprints.
     */
    public function requiredAnalyses(): array
    {
        return ['ownerDesign'];
    }

    /**
     * Resolve direct reads and getters before grouping distinct callable consumers.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        $types = DesignEvidence::types($project->files);
        $groups = [];
        foreach ($project->files as $file => $metrics) {
            $design = $metrics['ownerDesign'] ?? [];
            foreach ($design['decisions'] ?? [] as $decision) {
                $owner = $types[$decision['type']] ?? null;
                if ($owner === null || ! $this->hasKnownReads($owner, $decision['accesses'])) {
                    continue;
                }

                $key = $decision['type'].':'.$decision['key'];
                $consumers = $groups[$key] ?? [];
                $identity = $file.':'.$decision['name'];
                $consumer = $consumers[$identity] ?? [...$decision, 'file' => $file, 'owner' => $owner['name']];
                $consumers[$identity] = $consumer;
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
                message: 'The same compound question about '.$first['owner'].' is repeated in '.count($rows).' consumers: '.DesignEvidence::locations($rows).'.'
                        .' If these express one domain decision, introduce a named predicate on '.$first['owner'].'; a concern can organize related owner behavior.',
            );
        }
    }

    /**
     * Accept public fields and proven direct getters only on an uncomposed local owner.
     */
    private function hasKnownReads(array $owner, array $accesses): bool
    {
        if (! $owner['eligible']) {
            return false;
        }

        $properties = $owner['properties'];
        $methods = $owner['methods'];
        foreach ($accesses as [$kind, $name]) {
            if ($kind === 'property') {
                if (! ($properties[$name] ?? false)) {
                    return false;
                }

                continue;
            }

            $method = $methods[strtolower($name)] ?? null;
            if ($method === null || $method['getter'] === null || ! array_key_exists($method['getter'], $properties)) {
                return false;
            }
        }

        return true;
    }
}
