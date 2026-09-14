<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

use function implode;

use LogicException;

use function sprintf;

/**
 * Generates soda.php using `Soda::configure()->withPaths([...])->with([...])`.
 *
 * Each rule is emitted as a standalone class instance with its threshold in the constructor.
 *
 * @internal
 */
final class SodaInitFileEmitter
{
    /**
     * Expand the standard catalog into editable PHP rule instances.
     */
    public static function emit(): string
    {
        $uses = ['use Cosmira\Soda\Config\Soda;'];
        $items = [];
        $shortNames = [];

        foreach (RuleCatalog::definitions() as $ruleKey => $definition) {
            $shortName = self::shortName($definition['class']);
            self::assertUniqueShortName($shortNames, $shortName, $definition['class']);
            $shortNames[$shortName] = $definition['class'];
            $uses[] = 'use '.$definition['class'].';';
            $items[] = match ($ruleKey) {
                'max_arguments'                  => self::instantiate($shortName, $definition['arguments'], 'properties: $properties'),
                'max_properties_per_class'       => '$properties,',
                'boolean_methods_without_prefix' => $shortName.'::standard(),',
                default                          => self::instantiate($shortName, $definition['arguments']),
            };
        }

        $propertyRule = RuleCatalog::definitions()['max_properties_per_class'];
        $properties = self::instantiate(self::shortName($propertyRule['class']), $propertyRule['arguments']);
        $properties = rtrim($properties, ',');
        sort($uses);
        $usesBlock = implode("\n", array_unique($uses));
        $itemsBlock = implode("\n", array_map(fn (string $line) => '        '.$line, $items));

        return <<<PHP
<?php

declare(strict_types=1);

{$usesBlock}

\$properties = {$properties};

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
{$itemsBlock}
    ]);
PHP;
    }

    /**
     * Return the unqualified name used in generated PHP configuration.
     */
    private static function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }

    /**
     * @param array<string, string> $shortNames
     */
    private static function assertUniqueShortName(array $shortNames, string $shortName, string $fqcn): void
    {
        $isPresent = isset($shortNames[$shortName]);
        if (! $isPresent) {
            return;
        }

        throw new LogicException(sprintf(
            'Cannot emit soda.php because rule classes share short name %s: %s.',
            $shortName,
            implode(', ', [$shortNames[$shortName], $fqcn]),
        ));
    }

    /**
     * Render constructor arguments and an optional shared rule dependency.
     */
    private static function instantiate(string $class, array $arguments, ?string $dependency = null): string
    {
        $rendered = [];
        foreach ($arguments as $key => $argument) {
            $prefix = is_string($key) ? $key.': ' : '';
            $rendered[] = $prefix.var_export($argument, true);
        }

        if ($dependency !== null) {
            $rendered[] = $dependency;
        }

        return sprintf('new %s(%s),', $class, implode(', ', $rendered));
    }
}
