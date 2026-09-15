<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;

final class RuleCatalog
{
    /**
     * @return array<string, array{class: class-string<Check>, arguments: array, section: string, severity: string, default: int|float, label: string, advice: string, comparison: ?string, standard?: bool}>
     */
    public static function definitions(): array
    {
        return require __DIR__.'/../../resources/catalog.php';
    }

    /**
     * Keep optional checks discoverable without enabling them in the standard bundle.
     *
     * @return array<string, array{class: class-string<Check>, arguments: array, section: string, severity: string, default: int|float, label: string, advice: string, comparison: ?string, standard?: bool}>
     */
    public static function standardDefinitions(): array
    {
        return array_filter(self::definitions(), fn (array $entry): bool => $entry['standard'] ?? true);
    }

    /**
     * Create the standard rules, including readonly-data and boolean-prefix policies.
     *
     * @return list<Check>
     */
    public static function standard(): array
    {
        $definitions = self::standardDefinitions();
        $propertyRule = $definitions['max_properties_per_class'];
        $properties = new MaxPropertiesPerClass(...$propertyRule['arguments']);
        $checks = [];

        foreach ($definitions as $id => $entry) {
            $class = $entry['class'];
            $checks[] = match ($id) {
                'max_arguments'                     => new MaxArguments(...$entry['arguments'], properties: $properties),
                'max_properties_per_class'          => $properties,
                'boolean_methods_without_prefix'    => BooleanMethodPrefix::standard(),
                default                             => new $class(...$entry['arguments']),
            };
        }

        return $checks;
    }

    /**
     * Instantiate standalone constructor defaults, optionally selecting a catalog section.
     *
     * @return list<Check>
     */
    public static function checks(?string $section = null): array
    {
        $checks = [];
        foreach (self::standardDefinitions() as $entry) {
            $isOtherSection = $section !== null && $section !== $entry['section'];
            if ($isOtherSection) {
                continue;
            }

            $class = $entry['class'];
            $checks[] = new $class(...$entry['arguments']);
        }

        return $checks;
    }
}
