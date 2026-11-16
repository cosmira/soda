<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;

#[Rule(
    label: 'Layer mixing:',
    advice: 'This is a naming and directory convention, not a dependency analysis. Inspect actual responsibilities and call paths; renaming a folder alone does not improve architecture.',
    severity: 'error',
    default: 50,
)]
final class MaxLayerDominancePercentage extends Check
{
    /**
     * Files without a recognized role still contribute to the directory total.
     */
    private const PLAIN_ROLE = 'Plain';

    /**
     * Files declaring several recognized roles form a separate category.
     */
    private const MIXED_ROLE = 'Mixed';

    /**
     * Conventional architectural roles; arbitrary base classes are not layers.
     */
    private const ROLES = [
        'Controller', 'Service', 'Repository', 'Model', 'Job', 'Command',
        'Event', 'Listener', 'Policy', 'Middleware', 'Resource', 'Request',
    ];

    /**
     * Configure the dominance percentage and minimum directory size.
     *
     * @param int $threshold Threshold used to decide whether to report the measured value.
     * @param int $minFiles  Minimum namespace size required before evaluating directory roles.
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

        foreach ($this->aggregate($project->files) as $directory => $summary) {
            $violation = $this->violationForDirectory($directory, $summary);
            if ($violation instanceof Violation) {
                yield $violation;
            }
        }
    }

    /**
     * Count file roles within each directory while retaining unclassified files in its total.
     *
     * @param array<string, array<string, mixed>> $files
     *
     * @return array<string, array{file: string, fileCount: int, roleCounts: array<string, int>}>
     */
    private function aggregate(array $files): array
    {
        $directories = [];
        foreach ($files as $file => $metrics) {
            $directory = dirname($file);
            $directories[$directory] ??= [
                'file'       => $file,
                'fileCount'  => 0,
                'roleCounts' => [],
            ];

            $row = $directories[$directory];
            $row['fileCount']++;
            $role = $this->fileRole($metrics['classes'] ?? []);
            $counts = $row['roleCounts'];
            $counts[$role] = ($counts[$role] ?? 0) + 1;
            $row['roleCounts'] = $counts;
            $directories[$directory] = $row;
        }

        return $directories;
    }

    /**
     * Infer only explicit class-name roles, retaining other files in the directory total.
     *
     * @param array<string, array> $classes
     */
    private function fileRole(array $classes): string
    {
        $roles = [];
        foreach ($classes as $class => $declaration) {
            if ($declaration['kind'] !== 'class') {
                continue;
            }

            $role = $this->role($class);
            if ($role !== null) {
                $roles[] = $role;
            }
        }

        $roles = array_unique($roles);
        if ($roles === []) {
            return self::PLAIN_ROLE;
        }

        $hasSingleRole = count($roles) === 1;

        return $hasSingleRole ? array_pop($roles) : self::MIXED_ROLE;
    }

    /**
     * Match a declared role suffix without inferring a layer from inheritance.
     */
    private function role(string $class): ?string
    {
        foreach (self::ROLES as $role) {
            if (str_ends_with($class, $role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Report unexpected architectural-role dominance in a sufficiently large directory.
     *
     * @param array{file: string, fileCount: int, roleCounts: array<string, int>} $summary
     */
    private function violationForDirectory(string $directory, array $summary): ?Violation
    {
        $shouldReportDirectory = $this->shouldReportDirectory($summary);
        if (! $shouldReportDirectory) {
            return null;
        }

        [$dominantRole, $dominantCount] = $this->dominantRole($summary['roleCounts']);
        $isExpectedLayer = $dominantRole === self::PLAIN_ROLE || $this->isDirectoryNamedAfterRole($directory, $dominantRole);

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
            'Directory naming convention: %s suffix appears in %.1f%% of %d PHP files in %s; other roles: %s. This does not measure dependency direction.',
            $dominantRole,
            $share,
            $summary['fileCount'],
            $directory,
            $this->otherRoles($otherRoles),
        ));
    }

    /**
     * Check that the directory has enough files for a meaningful dominance measurement.
     *
     * @param array{file: string, fileCount: int, roleCounts: array<string, int>} $summary
     */
    private function shouldReportDirectory(array $summary): bool
    {
        $roles = $summary['roleCounts'];
        unset($roles[self::PLAIN_ROLE]);

        return $summary['fileCount'] >= max(1, $this->minFiles) && count($roles) > 1;
    }

    /**
     * Select the most frequent architectural role and its file count.
     *
     * @param array<string, int> $roleCounts
     *
     * @return array{0: string, 1: int}
     */
    private function dominantRole(array $roleCounts): array
    {
        arsort($roleCounts);
        $role = array_key_first($roleCounts) ?? self::PLAIN_ROLE;

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
     * List the non-dominant roles to explain the directory composition.
     *
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
