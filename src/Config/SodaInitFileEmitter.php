<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Config;

use Bunnivo\Soda\Quality\RuleCatalog\RuleCatalog;
use Bunnivo\Soda\Quality\RuleCatalog\RuleInitDefinition;

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
     * @return list<string>
     */
    public static function ruleIds(): array
    {
        return array_keys(RuleCatalog::initDefinitions());
    }

    /**
     * @param array<string, array<string, mixed>> $sections Section name → rule_id → value
     */
    public static function emit(array $sections): string
    {
        $uses = ['use Bunnivo\Soda\Config\Soda;'];
        $items = [];

        foreach (RuleCatalog::definitions() as $ruleKey => $definition) {
            $init = $definition->fields->init;
            $section = $sections[$definition->fields->identity->section] ?? [];
            $value = $section[$ruleKey] ?? null;

            if ($value === null || $init === null) {
                continue;
            }

            $shortName = self::shortName($init->class);
            $uses[] = 'use '.$init->class.';';
            $items[] = self::instantiate($shortName, $init, $value);
        }

        sort($uses);
        $usesBlock = implode("\n", array_unique($uses));
        $itemsBlock = implode("\n", array_map(fn (string $line) => '        '.$line, $items));

        return <<<PHP
<?php

declare(strict_types=1);

{$usesBlock}

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
{$itemsBlock}
    ]);
PHP;
    }

    private static function shortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }

    private static function instantiate(string $class, RuleInitDefinition $init, mixed $value): string
    {
        return match ($init->constructor) {
            'none'       => sprintf('new %s(),', $class),
            'layer'      => self::layerInstance($class, $value),
            'range'      => sprintf('new %s(min: %d, max: %d),', $class, $init->min, self::intThreshold($value)),
            'visibility' => sprintf("new %s(['public']),", $class),
            default      => sprintf('new %s(%d),', $class, self::intThreshold($value)),
        };
    }

    private static function layerInstance(string $class, mixed $value): string
    {
        $minFiles = is_array($value) ? (int) ($value['min_files'] ?? 4) : 4;

        return sprintf('new %s(%d, %d),', $class, self::intThreshold($value), $minFiles);
    }

    private static function intThreshold(mixed $value): int
    {
        return is_array($value) ? (int) ($value['threshold'] ?? 0) : (int) $value;
    }
}
