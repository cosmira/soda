<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Design;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\Representation\RepresentationFlow;
use Cosmira\Soda\Contracts\ResearchCheck;
use Cosmira\Soda\Reporting\Rule;
use InvalidArgumentException;

/**
 * Investigates application value paths without turning representation changes into violations.
 */
#[Rule(
    label: 'Representation research candidates:',
    advice: 'Inspect the actual data path, contracts and boundary purpose before changing its representation.',
)]
final class RepresentationChurn extends ResearchCheck
{
    /**
     * Bound call expansion, work and retained witnesses for this research run.
     */
    public function __construct(
        /**
         * Maximum nested callable expansions per witness.
         */
        private readonly int $maxDepth = 12,
        /**
         * Maximum interpreted IR operations across the run.
         */
        private readonly int $maxSteps = 2000,
        /**
         * Maximum distinct retained reconstruction witnesses.
         */
        private readonly int $maxCandidates = 100,
    ) {
        throw_if(min($maxDepth, $maxSteps, $maxCandidates) < 1, InvalidArgumentException::class, 'Research limits must be positive.');
    }

    /**
     * Identify the optional representation consistency investigation.
     */
    public function id(): string
    {
        return 'representation_churn';
    }

    /**
     * Collect value IR only when this research check is selected.
     */
    public function requiredAnalyses(): array
    {
        return ['representationFlow'];
    }

    /**
     * Return structural witnesses, explicit gaps and measured analysis coverage.
     */
    public function researchProject(ProjectFacts $project): array
    {
        return (new RepresentationFlow($this->maxDepth, $this->maxSteps, $this->maxCandidates))->analyze($project);
    }
}
