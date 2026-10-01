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
        $archives = [];
        foreach (array_keys($metadata) as $path) {
            $sources = self::sourcesFrom($path);
            $archives = [...$archives, ...$sources->archives];
            foreach ($sources->prefixes as $prefix => $roots) {
                $prefixes[$prefix] = [...($prefixes[$prefix] ?? []), ...$roots];
            }
        }

        uksort($prefixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return new self($prefixes, array_values(array_unique($archives)));
    }

    /**
     * Convert package installation paths and their PSR-4 declarations into source roots.
     */
    private static function sourcesFrom(string $path): self
    {
        $content = file_get_contents($path);
        $installed = $content === false ? [] : json_decode($content, true);
        $prefixes = [];
        $archives = [];
        foreach ($installed['packages'] ?? [] as $package) {
            $autoload = $package['autoload'] ?? [];
            $installPath = $package['install-path'] ?? null;
            if (! is_string($installPath)) {
                continue;
            }

            $packageRoot = dirname($path).'/'.$installPath;
            $packageArchives = glob($packageRoot.'/*.{phar,zip,tar}', GLOB_BRACE);
            $archives = [...$archives, ...($packageArchives === false ? [] : $packageArchives)];

            foreach ($autoload['psr-4'] ?? [] as $prefix => $roots) {
                $directories = $prefixes[$prefix] ?? [];
                foreach ((array) $roots as $root) {
                    $directories[] = dirname($path).'/'.$installPath.'/'.$root;
                }

                $prefixes[$prefix] = $directories;
            }
        }

        return new self($prefixes, $archives);
    }

    /**
     * Store only source directories declared by Composer.
     *
     * @param array<string, list<string>> $prefixes Composer namespace prefixes.
     * @param list<string>                $archives Archives shipped by installed Composer packages.
     */
    private function __construct(private array $prefixes, private array $archives) {}

    /**
     * Locate source candidates; callers verify the declared qualified name.
     *
     * @return list<string>
     */
    public function locate(string $class): array
    {
        $paths = [];

        foreach ($this->prefixes as $prefix => $roots) {
            $matchesPrefix = str_starts_with(strtolower($class), strtolower($prefix));
            if (! $matchesPrefix) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            foreach ($roots as $root) {
                $path = rtrim($root, '/').'/'.$relative;
                if (is_file($path)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths !== [] ? $paths : $this->archiveCandidates($class);
    }

    /**
     * @return list<string>
     */
    private function archiveCandidates(string $class): array
    {
        $name = basename(str_replace('\\', '/', $class)).'.php';
        $paths = [];

        foreach ($this->archives as $archive) {
            $path = realpath($archive);

            if ($path === false) {
                continue;
            }

            foreach ($this->archiveFiles($path) as $file) {
                $matchesName = strcasecmp($file->getFilename(), $name) === 0;
                if ($file->isFile() && $matchesName) {
                    $paths[] = $file->getPathname();
                }
            }
        }

        return $paths;
    }

    /**
     * @return iterable<\PharFileInfo>
     */
    private function archiveFiles(string $path): iterable
    {
        try {
            try {
                $contents = new \Phar($path);
            } catch (\UnexpectedValueException) {
                $contents = new \PharData($path);
            }

            yield from new \RecursiveIteratorIterator($contents);
        } catch (\UnexpectedValueException) {
            return;
        }
    }
}
