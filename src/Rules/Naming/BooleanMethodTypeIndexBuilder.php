<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use function array_fill_keys;
use function array_values;

use Cosmira\Soda\Analysis\ProjectFacts;

use function is_array;
use function is_string;

/**
 * @internal
 */
final class BooleanMethodTypeIndexBuilder
{
    /**
     * @return array<string, array{inherits: list<string>, methods: array<string, true>}>
     */
    public static function build(ProjectFacts $project): array
    {
        $index = [];

        foreach ($project->files as $metrics) {
            $naming = $metrics['naming'] ?? null;
            $types = is_array($naming) ? ($naming['types'] ?? null) : null;
            $isArray = is_array($types);

            if (! $isArray) {
                continue;
            }

            foreach ($types as $typeData) {
                $entry = self::typeIndexEntry($typeData);

                if ($entry === null) {
                    continue;
                }

                $index[$entry['name']] = $entry['data'];
            }
        }

        return $index;
    }

    /**
     * @return array{name: string, data: array{inherits: list<string>, methods: array<string, true>}}|null
     */
    private static function typeIndexEntry(mixed $typeData): ?array
    {
        $isArray = is_array($typeData);
        if (! $isArray) {
            return null;
        }

        $name = $typeData['name'] ?? null;
        $isString = is_string($name);

        if (! $isString) {
            return null;
        }

        return [
            'name' => $name,
            'data' => [
                'inherits' => self::stringList($typeData['inherits'] ?? []),
                'methods'  => array_fill_keys(self::stringList($typeData['methods'] ?? []), true),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        $isArray = is_array($values);
        if (! $isArray) {
            return [];
        }

        return array_values(array_filter(
            $values,
            static fn (mixed $name): bool => is_string($name) && $name !== '',
        ));
    }
}
