<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class MaxLayerDominancePercentage extends Check
{
    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Configure the dominance percentage and minimum directory size.
     */
    public function __construct(private readonly int $threshold = 80, private readonly int $minFiles = 4) {}

    /**
     * Check directories after all class names have been collected.
     *
     * @return iterable<Violation>
     */
    public function checkProject(ProjectFacts $project): iterable
    {
        if ($this->threshold <= 0) {
            return;
        }

        foreach (DirectoryRoles::aggregate($project->files) as $directory => $summary) {
            $violation = $this->violationForDirectory($directory, $summary);
            if ($violation instanceof Violation) {
                yield $violation;
            }
        }
    }

    /**
     * @param array{file: string, fileCount: int, roleCounts: array<string, int>} $summary
     */
    private function violationForDirectory(string $directory, array $summary): ?Violation
    {
        $shouldReportDirectory = $this->shouldReportDirectory($summary);
        if (! $shouldReportDirectory) {
            return null;
        }

        [$dominantRole, $dominantCount] = $this->dominantRole($summary['roleCounts']);
        $isExpectedLayer = $dominantRole === DirectoryRoles::PLAIN_ROLE || $this->isDirectoryNamedAfterRole($directory, $dominantRole);

        if ($isExpectedLayer) {
            return null;
        }

        $share = ($dominantCount / $summary['fileCount']) * 100;

        if ($share < $this->threshold) {
            return null;
        }

        $otherRoles = $summary['roleCounts'];
        unset($otherRoles[$dominantRole]);

        return new Violation(rule: $this->id(), file: $summary['file'], value: (int) round($share), threshold: $this->threshold, class: $directory, message: sprintf(
            'Layer mixing: %s dominates %.1f%% of %d PHP files in %s; other types: %s',
            $dominantRole,
            $share,
            $summary['fileCount'],
            $directory,
            $this->otherRoles($otherRoles),
        ));
    }

    /**
     * @param array{file: string, fileCount: int, roleCounts: array<string, int>} $summary
     */
    private function shouldReportDirectory(array $summary): bool
    {
        $roles = $summary['roleCounts'];
        unset($roles[DirectoryRoles::PLAIN_ROLE]);

        return $summary['fileCount'] >= max(1, $this->minFiles) && count($roles) > 1;
    }

    /**
     * @param array<string, int> $roleCounts
     *
     * @return array{0: string, 1: int}
     */
    private function dominantRole(array $roleCounts): array
    {
        arsort($roleCounts);
        $role = array_key_first($roleCounts) ?? DirectoryRoles::PLAIN_ROLE;

        return [$role, $roleCounts[$role] ?? 0];
    }

    /**
     * A directory explicitly named for its dominant role is an intentional layer.
     */
    private function isDirectoryNamedAfterRole(string $directory, string $dominantRole): bool
    {
        return str_contains(strtolower(basename($directory)), strtolower($dominantRole));
    }

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'max_layer_dominance_percentage';
    }

    /**
     * @param array<string, int> $roleCounts
     */
    private function otherRoles(array $roleCounts): string
    {
        arsort($roleCounts);
        $parts = [];

        foreach ($roleCounts as $role => $count) {
            $parts[] = sprintf('%s=%d', $role, $count);
        }

        return $parts !== [] ? implode(', ', $parts) : 'none';
    }
}
