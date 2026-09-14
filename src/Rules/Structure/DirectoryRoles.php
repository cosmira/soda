<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use function array_unique;
use function count;
use function dirname;

/**
 * @internal
 */
final class DirectoryRoles
{
    /**
     * Files without a recognized role still contribute to the directory total.
     */
    public const string PLAIN_ROLE = 'Plain';

    /**
     * Files declaring several recognized roles form a separate category.
     */
    private const string MIXED_ROLE = 'Mixed';

    /**
     * @param array<string, array<string, mixed>> $files
     *
     * @return array<string, array{file: string, fileCount: int, roleCounts: array<string, int>}>
     */
    public static function aggregate(array $files): array
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
            $role = self::fileRole($metrics['classes'] ?? []);
            $counts = $row['roleCounts'];
            $counts[$role] = ($counts[$role] ?? 0) + 1;
            $row['roleCounts'] = $counts;
            $directories[$directory] = $row;
        }

        return $directories;
    }

    /**
     * Conventional architectural roles; arbitrary base classes are not layers.
     */
    private const array ROLES = [
        'Controller', 'Service', 'Repository', 'Model', 'Job', 'Command',
        'Event', 'Listener', 'Policy', 'Middleware', 'Resource', 'Request',
    ];

    /**
     * Infer only explicit class-name roles, retaining helpers in the file total.
     *
     * @param array<string, array> $classes
     */
    private static function fileRole(array $classes): string
    {
        $roles = [];
        foreach ($classes as $class => $declaration) {
            if ($declaration['kind'] !== 'class') {
                continue;
            }

            $role = self::role($class);
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
    private static function role(string $class): ?string
    {
        foreach (self::ROLES as $role) {
            if (str_ends_with($class, $role)) {
                return $role;
            }
        }

        return null;
    }
}
