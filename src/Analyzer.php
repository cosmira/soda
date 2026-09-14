<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Reporting\QualityResult;

/**
 * Facade-style entry for code-quality rule evaluation.
 */
final class Analyzer
{
    /**
     * @param non-empty-string $path
     */
    public static function file(string $path): QualityAnalysisBuilder
    {
        return new QualityAnalysisBuilder([$path]);
    }

    /**
     * @param list<non-empty-string> $paths
     */
    public static function paths(array $paths): QualityAnalysisBuilder
    {
        return new QualityAnalysisBuilder($paths);
    }

    /**
     * @param list<non-empty-string> $paths
     * @param non-empty-string|null  $configPath
     *
     * @throws ConfigException
     */
    public static function analyze(array $paths, ?string $configPath = null): QualityResult
    {
        $builder = self::paths($paths)->config($configPath);

        return $builder->analyse();
    }
}
