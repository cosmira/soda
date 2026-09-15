<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\Composition\ComposedClassMetrics;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

/**
 * Limits class composition without rewarding movement into traits.
 */
final class MaxEffectiveClassLength extends Check
{
    /**
     * Set a positive limit for the composed class.
     */
    public function __construct(private readonly int $limit = 500)
    {
        throw_if($limit < 1, \InvalidArgumentException::class, 'The effective class limit must be positive.');
    }

    /**
     * Evaluate the complete source set after all files have been collected.
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        foreach ((new ComposedClassMetrics)->collect($project) as $class) {
            if ($class['loc'] > $this->limit) {
                yield new Violation(
                    rule: $this->id(), file: $class['file'],
                    value: $class['loc'], threshold: $this->limit, class: $class['name'], line: $class['line'],
                    message: 'Class source lines including traits: '.$class['loc'].'. Keep the whole owning class within its limit.',
                );
            }
        }
    }

    /**
     * Return the stable rule identifier.
     */
    public function id(): string
    {
        return 'max_effective_class_length';
    }

    /**
     * Trait relationships and source sizes are structural facts.
     */
    public function requiredAnalyses(): array
    {
        return ['structure'];
    }
}
