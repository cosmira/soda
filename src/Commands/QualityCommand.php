<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Reporting\QualityJsonReportFormatter;
use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Reporting\ReportFormatter;
use Cosmira\Soda\Reporting\RuleMetadata;

use function file_put_contents;
use function getcwd;

use Illuminate\Console\Command;

use function is_string;
use function json_encode;
use function preg_match;
use function rtrim;

use SebastianBergmann\FileIterator\Facade;

final class QualityCommand extends Command
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private readonly ?Runner $qualityAnalysis = null,
    ) {
        parent::__construct();
    }

    /**
     * Stores signature for this analysis instance.
     */
    protected $signature = 'quality
        {path?* : Directory or directories to analyse}
        {--suffix= : Include files with names ending in suffix (default: .php)}
        {--exclude=* : Exclude files with path in their path}
        {--config= : Path to soda.php}
        {--report-json= : Write quality report to JSON file}
    ';

    /**
     * Stores description for this analysis instance.
     */
    protected $description = 'Analyse code quality and check against configured thresholds';

    /**
     * Execute the console command and return its exit status.
     */
    public function handle(): int
    {
        /** @var list<non-empty-string> $directories */
        $directories = (array) $this->argument('path');

        $root = getcwd();
        $hasRoot = $root !== false && $root !== '';
        $root = ($hasRoot) ? $root : '/';

        try {
            $configPath = $this->resolveConfigPath();
            $loader = new ConfigLoader;
            $config = null;
            if ($directories === []) {
                $config = $loader->resolve([$root], $configPath);
                $directories = $config->paths();
            }

            if ($directories === []) {
                $this->error('No directory specified. Add withPaths([...]) to soda.php or pass a path.');

                return self::FAILURE;
            }

            $files = $this->resolveFiles($directories);

            if ($files === []) {
                $this->error('No files found to scan');

                return self::FAILURE;
            }

            $config ??= $loader->resolve($files, $configPath);
            $result = ($this->qualityAnalysis ?? new Runner)->check($files, $config);
        } catch (ConfigException $configException) {
            $this->error($configException->getMessage());

            return self::FAILURE;
        }

        (new ReportFormatter(RuleMetadata::default()))->write($this->output, $result, $root);
        $this->writeReportJson($result);

        return $result->isPassing() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Vendor code is not part of the project being assessed. FileIterator expects
     * exclusion paths rather than path fragments, so expand relative CLI values
     * against every requested analysis root.
     *
     * @param list<non-empty-string> $directories
     * @param list<non-empty-string> $requested
     *
     * @return list<non-empty-string>
     */
    private function exclusionPaths(array $directories, array $requested): array
    {
        $relative = ['vendor', ...$requested];
        $paths = [];

        foreach ($relative as $exclude) {
            if ($this->isAbsolutePath($exclude)) {
                $paths[] = $exclude;

                continue;
            }

            foreach ($directories as $directory) {
                $paths[] = rtrim($directory, '/\\').'/'.$exclude;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Determine whether absolute path applies to the supplied input.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    /**
     * @return non-empty-string|null
     */
    private function resolveConfigPath(): ?string
    {
        $configOpt = $this->option('config');
        $isMissingConfig = ! is_string($configOpt) || $configOpt === '';

        if ($isMissingConfig) {
            return null;
        }

        return $configOpt;
    }

    /**
     * @param list<non-empty-string> $directories
     *
     * @return list<non-empty-string>
     */
    private function resolveFiles(array $directories): array
    {
        $suffixOpt = $this->option('suffix');
        $suffixes = $suffixOpt === null ? ['.php'] : array_merge(['.php'], (array) $suffixOpt);
        /** @var list<non-empty-string> $suffixes */
        $exclude = $this->exclusionPaths(
            $directories,
            (array) ($this->option('exclude') ?? []),
        );
        /** @var list<non-empty-string> $exclude */

        return (new Facade)->getFilesAsArray($directories, $suffixes, '', $exclude);
    }

    /**
     * Write report json using the current analysis state.
     */
    private function writeReportJson(QualityResult $result): void
    {
        $reportJson = $this->option('report-json');
        $isMissingReport = ! is_string($reportJson) || $reportJson === '';

        if ($isMissingReport) {
            return;
        }

        $data = (new QualityJsonReportFormatter(RuleMetadata::default()))->format($result);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($reportJson, $json);
    }
}
