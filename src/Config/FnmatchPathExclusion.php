<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

/**
 * Exclude file paths by absolute/resolved prefix and/or fnmatch patterns (full path + basename).
 *
 * Stateless aside from configured patterns; safe to share across rules.
 */
final readonly class FnmatchPathExclusion
{
    /**
     * @param list<string> $patterns     fnmatch patterns (full path and basename checked per pattern)
     * @param list<string> $pathPrefixes file path must be under one of these when resolved
     */
    public function __construct(
        private array $patterns,
        private array $pathPrefixes,
    ) {}

    /**
     * Determine whether excluded path applies to the supplied input.
     */
    public function isExcludedPath(string $file): bool
    {
        $isExcludedPrefix = $this->isUnderAnyPrefix($this->resolvedPath($file));
        if ($isExcludedPrefix) {
            return true;
        }

        return $this->isExcludedByPattern($file);
    }

    /**
     * Determine whether under any prefix applies to the supplied input.
     */
    private function isUnderAnyPrefix(string $resolvedFile): bool
    {
        foreach ($this->pathPrefixes as $prefix) {
            if ($this->isResolvedPathUnderPrefix($resolvedFile, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Select path-matching flags for the configured exclusion pattern.
     */
    private function fnmatchFlags(): int
    {
        $flags = FNM_PATHNAME;
        if (defined('FNM_CASEFOLD')) {
            $flags |= FNM_CASEFOLD;
        }

        return $flags;
    }

    /**
     * Determine whether resolved path under prefix applies to the supplied input.
     */
    private function isResolvedPathUnderPrefix(string $resolvedFile, string $prefix): bool
    {
        $root = rtrim(str_replace('\\', '/', $this->resolvedPath($prefix)), '/');
        if ($root === '') {
            return false;
        }

        $path = rtrim($resolvedFile, '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    /**
     * Normalize a file path for comparison with exclusion prefixes.
     */
    private function resolvedPath(string $path): string
    {
        $slash = str_replace('\\', '/', $path);
        $real = realpath($path);
        if ($real !== false) {
            return str_replace('\\', '/', $real);
        }

        $cwd = getcwd();
        $isRelativePath = $cwd !== false && $slash !== '' && ! str_starts_with($slash, '/');
        if ($isRelativePath) {
            $joined = realpath($cwd.DIRECTORY_SEPARATOR.$slash);
            if ($joined !== false) {
                return str_replace('\\', '/', $joined);
            }
        }

        return $slash;
    }

    /**
     * Determine whether excluded by pattern applies to the supplied input.
     */
    private function isExcludedByPattern(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        $base = basename($file);
        $flags = $this->fnmatchFlags();

        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $normalized, $flags)) {
                return true;
            }

            $matchesBasename = fnmatch($pattern, $base, $flags & ~FNM_PATHNAME);

            if ($matchesBasename) {
                return true;
            }
        }

        return false;
    }
}
