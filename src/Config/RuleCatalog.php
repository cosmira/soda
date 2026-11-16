<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class RuleCatalog
{
    /**
     * Discover concrete checks and read their class attributes without constructing optional rules.
     *
     * @return array<string, array{class: class-string<Check>, section: string, severity: string, default: int|float, label: string, advice: string, comparison: ?string, standard: bool}>
     */
    public static function definitions(): array
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }

        $standard = array_fill_keys(array_map(fn (Check $check): string => $check::class, self::standard()), true);
        $definitions = [];
        foreach (self::classes() as $reflection) {
            $class = $reflection->getName();
            $attribute = $reflection->getAttributes(Rule::class);
            throw_if($attribute === [], LogicException::class, 'Missing Rule attribute on '.$class.'.');

            $metadata = $attribute[0]->newInstance();
            $id = $reflection->newInstanceWithoutConstructor()->id();
            throw_if(isset($definitions[$id]), LogicException::class, 'Duplicate rule identifier: '.$id.'.');

            $section = explode('\\', substr($class, strlen('Cosmira\\Soda\\Rules\\')))[0];
            $definitions[$id] = [
                'class'      => $class,
                'section'    => $metadata->section ?? (in_array($section, ['Complexity', 'Naming', 'Laravel'], true) ? strtolower($section) : 'structural'),
                'severity'   => $metadata->severity,
                'default'    => $metadata->default,
                'label'      => $metadata->label,
                'advice'     => $metadata->advice,
                'comparison' => $metadata->comparison,
                'standard'   => isset($standard[$class]),
            ];
        }

        ksort($definitions);

        $cached = $definitions;

        return $definitions;
    }

    /**
     * Standard definitions follow the explicit configuration's execution order.
     *
     * @return array<string, array{class: class-string<Check>, section: string, severity: string, default: int|float, label: string, advice: string, comparison: ?string, standard: bool}>
     */
    public static function standardDefinitions(): array
    {
        $definitions = self::definitions();
        $standard = [];
        foreach (self::standard() as $check) {
            $standard[$check->id()] = $definitions[$check->id()];
        }

        return $standard;
    }

    /**
     * Load the same explicit configuration distributed by init.
     *
     * @return list<Check>
     */
    public static function standard(): array
    {
        return (require __DIR__.'/../../resources/standard.php')->checks();
    }

    /**
     * Select standard sections, or all checks when a section is exclusively optional.
     *
     * @return list<Check>
     */
    public static function checks(?string $section = null): array
    {
        $checks = self::standard();
        if ($section === null) {
            return $checks;
        }

        $definitions = self::definitions();
        $sections = array_column($definitions, 'section', 'class');
        $selected = array_values(array_filter($checks, fn (Check $check): bool => $sections[$check::class] === $section));
        if ($selected !== []) {
            return $selected;
        }

        foreach ($definitions as $entry) {
            if ($entry['section'] === $section) {
                $selected[] = new $entry['class'];
            }
        }

        return $selected;
    }

    /**
     * Resolve only concrete checks from PSR-4 paths, ignoring sibling algorithm classes.
     *
     * @return iterable<ReflectionClass<Check>>
     */
    private static function classes(): iterable
    {
        $directory = __DIR__.'/../Rules/';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = 'Cosmira\\Soda\\Rules\\'.str_replace('/', '\\', substr($file->getPathname(), strlen($directory), -4));
            if (! is_subclass_of($class, Check::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if (! $reflection->isAbstract()) {
                yield $reflection;
            }
        }
    }
}
