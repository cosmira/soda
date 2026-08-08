<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Commands;

use Bunnivo\Soda\Analyzer;
use Bunnivo\Soda\Formatter\JsonResultFormatter;
use Bunnivo\Soda\Quality\Config\ConfigLoader;
use Bunnivo\Soda\Quality\ConfigException;
use Bunnivo\Soda\Quality\Engine\QualityAnalysisContract;
use Bunnivo\Soda\Quality\QualityResult;
use Bunnivo\Soda\Quality\Report\ReportFormatter;
use Bunnivo\Soda\Quality\Report\RuleMetadata;

use function file_put_contents;
use function getcwd;

use Illuminate\Console\Command;

use function is_readable;
use function is_string;
use function json_encode;

use SebastianBergmann\FileIterator\Facade;

final class QualityCommand extends Command
{
    public function __construct(
        private readonly ?QualityAnalysisContract $qualityAnalysis = null,
    ) {
        parent::__construct();
    }

    protected $signature = 'quality
        {path?* : Directory or directories to analyse}
        {--suffix= : Include files with names ending in suffix (default: .php)}
        {--exclude=* : Exclude files with path in their path}
        {--debug : Print debugging information}
        {--config= : Path to soda.php}
        {--report-json= : Write quality report to JSON file}
    ';

    protected $description = 'Analyse code quality and check against configured thresholds';

    /**
     * @throws ConfigException
     */
    public function handle(): int
    {
        /** @var list<non-empty-string> $directories */
        $directories = (array) $this->argument('path');

        $configPath = $this->resolveConfigPath();
        $directories = $this->directoriesFromArgumentsOrConfig($directories, $configPath);

        if ($directories === []) {
            $this->error('No directory specified. Add withPaths([...]) to soda.php or pass a path.');

            return self::FAILURE;
        }

        $files = $this->resolveFiles($directories);

        if ($files === []) {
            $this->error('No files found to scan');

            return self::FAILURE;
        }

        $result = Analyzer::paths($files)
            ->debug((bool) $this->option('debug'))
            ->config($configPath)
            ->run($this->qualityAnalysis);

        $root = getcwd();
        $root = ($root !== false && $root !== '') ? $root : '/';
        (new ReportFormatter(RuleMetadata::default()))->write($this->output, $result, $root);
        $this->writeReportJson($result);

        return $result->isPassing() ? self::SUCCESS : self::FAILURE;
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
        $exclude = (array) ($this->option('exclude') ?? []);
        /** @var list<non-empty-string> $exclude */

        return (new Facade)->getFilesAsArray($directories, $suffixes, '', $exclude);
    }

    /**
     * @return non-empty-string|null
     */
    private function resolveConfigPath(): ?string
    {
        $configOpt = $this->option('config');

        if (! is_string($configOpt) || $configOpt === '') {
            return null;
        }

        return $configOpt;
    }

    /**
     * @param list<non-empty-string> $directories
     *
     * @return list<non-empty-string>
     */
    private function directoriesFromArgumentsOrConfig(array $directories, ?string $configPath): array
    {
        if ($directories !== []) {
            return $directories;
        }

        $path = $configPath ?? $this->defaultConfigPath();

        if ($path === null) {
            return [];
        }

        return (new ConfigLoader)->load($path)->paths;
    }

    /**
     * @return non-empty-string|null
     */
    private function defaultConfigPath(): ?string
    {
        $cwd = getcwd();

        if ($cwd === false || $cwd === '') {
            return null;
        }

        $path = $cwd.'/soda.php';

        return is_readable($path) ? $path : null;
    }

    private function writeReportJson(QualityResult $result): void
    {
        $reportJson = $this->option('report-json');

        if (! is_string($reportJson) || $reportJson === '') {
            return;
        }

        $data = [
            'schema_version' => 2,
            'metrics'        => (new JsonResultFormatter)->format($result->metrics),
            'violations'     => $result->violations->map(fn ($v) => $v->toArray())->all(),
        ];
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($reportJson, $json);
    }
}
