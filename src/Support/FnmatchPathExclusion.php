<?php

declare(strict_types=1);

namespace Cosmira\Soda\Support;

/**
 * Exclude file paths by absolute/resolved prefix and/or fnmatch patterns (full path + basename).
 *
 * Stateless aside from configured patterns; safe to share across rules.
 */
final readonly class FnmatchPathExclusion
{
    /**
     * Configure normalized path prefixes and glob patterns excluded from analysis.
     *
     * @param list<string> $patterns     fnmatch patterns (full path and basename checked per pattern)
     * @param list<string> $pathPrefixes Literal or masked directory boundaries; wildcards match one path segment
     * @param int          $flags        Flags controlling wildcard matching for file patterns.
     */
    public function __construct(
        /**
         * Glob patterns identifying paths exempted from the configured rule.
         */
        private array $patterns,
        /**
         * Normalized directory prefixes identifying exempted source boundaries.
         */
        private array $pathPrefixes,
        /**
         * Pattern flags preserve segment boundaries unless the caller allows directory separators.
         */
        private int $flags = FNM_PATHNAME,
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
        $flags = $this->flags;
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
        if ($prefix === '') {
            return false;
        }

        $root = rtrim(str_replace('\\', '/', $this->resolvedPath($prefix)), '/');
        $path = rtrim($resolvedFile, '/');

        $boundary = $root.'/';
        if ($path === $root || str_starts_with($path, $boundary)) {
            return true;
        }

        while (true) {
            if (fnmatch($root, $path, FNM_PATHNAME)) {
                return true;
            }

            $parent = dirname($path);
            if ($path === $parent) {
                return false;
            }

            $path = $parent;
        }
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

        return $this->resolvedPattern($slash);
    }

    /**
     * Resolve the literal directory before the first wildcard, preserving the mask.
     */
    private function resolvedPattern(string $path): string
    {
        $length = strcspn($path, '*?[');
        if ($length === strlen($path)) {
            return $path;
        }

        $boundary = strrpos(substr($path, 0, $length), '/');
        if ($boundary === false) {
            return $path;
        }

        $directory = realpath(substr($path, 0, $boundary + 1));
        if ($directory === false) {
            return $path;
        }

        return rtrim(str_replace('\\', '/', $directory), '/').'/'.substr($path, $boundary + 1);
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
