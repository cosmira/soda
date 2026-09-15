<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis\Dependencies;

/**
 * Reads Composer's JSON package metadata without loading its executable autoloader.
 */
final readonly class ComposerTypeSources
{
    /**
     * Discover installed PSR-4 sources enclosing the analysed files.
     */
    public static function discover(array $files): self
    {
        $metadata = [];
        foreach ($files as $file) {
            $directory = dirname($file);
            while (dirname($directory) !== $directory) {
                $path = $directory.'/vendor/composer/installed.json';
                if (is_file($path)) {
                    $metadata[$path] = true;

                    break;
                }

                $directory = dirname($directory);
            }
        }

        $prefixes = [];
        foreach (array_keys($metadata) as $path) {
            foreach (self::prefixesFrom($path) as $prefix => $roots) {
                $prefixes[$prefix] = [...($prefixes[$prefix] ?? []), ...$roots];
            }
        }

        uksort($prefixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return new self($prefixes);
    }

    /**
     * Convert package installation paths and their PSR-4 declarations into source roots.
     */
    private static function prefixesFrom(string $path): array
    {
        $content = file_get_contents($path);
        $installed = $content === false ? [] : json_decode($content, true);
        $prefixes = [];
        foreach ($installed['packages'] ?? [] as $package) {
            $autoload = $package['autoload'] ?? [];
            $installPath = $package['install-path'] ?? null;
            if (! is_string($installPath)) {
                continue;
            }

            foreach ($autoload['psr-4'] ?? [] as $prefix => $roots) {
                $directories = $prefixes[$prefix] ?? [];
                foreach ((array) $roots as $root) {
                    $directories[] = dirname($path).'/'.$installPath.'/'.$root;
                }

                $prefixes[$prefix] = $directories;
            }
        }

        return $prefixes;
    }

    /**
     * Store only source directories declared by Composer.
     */
    private function __construct(private array $prefixes) {}

    /**
     * Resolve a declared class name to a source file, respecting longest prefixes first.
     */
    public function locate(string $class): ?string
    {
        foreach ($this->prefixes as $prefix => $roots) {
            $matchesPrefix = str_starts_with(strtolower($class), strtolower($prefix));
            if (! $matchesPrefix) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            foreach ($roots as $root) {
                $path = rtrim($root, '/').'/'.$relative;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
