<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Reporting\QualityResult;

/**
 * Fluent entry for running quality analysis (reads like a sentence).
 */
final class QualityAnalysisBuilder
{
    /**
     * Stores config path for this analysis instance.
     */
    private ?string $configPath = null;

    /**
     * @param list<non-empty-string> $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {}

    /**
     * @param non-empty-string|null $path
     */
    public function config(?string $path): self
    {
        $this->configPath = $path;

        return $this;
    }

    /**
     * @throws ConfigException
     */
    public function analyse(?Runner $engine = null): QualityResult
    {
        $engine ??= new Runner;

        return $engine->analyse($this->paths, $this->configPath);
    }
}
