<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Error;

class Runner
{
    /**
     * Supply the PHP fact collector used for this analysis run.
     */
    public function __construct(private readonly FactCollector $collector = new FactCollector()) {}

    /**
     * Load configuration and use one path for file and project checks.
     *
     * @param list<non-empty-string> $files
     */
    public function analyse(array $files, ?string $configPath = null): QualityResult
    {
        return $this->check($files, (new ConfigLoader)->resolve($files, $configPath));
    }

    /**
     * Execute configured rules over each parsed file, then the scalar project facts.
     *
     * @param list<non-empty-string> $files
     */
    public function check(array $files, SodaConfig $config): QualityResult
    {
        $checks = $config->checks();
        $required = $this->requiredAnalyses($checks);
        $project = new ProjectFacts();
        $violations = [];
        foreach (array_unique($files) as $path) {
            try {
                $file = $this->collector->collect($path, $required);
                foreach ($checks as $check) {
                    array_push($violations, ...$check->checkFile($file));
                }

                $project->add($file);
                unset($file);
            } catch (ParserException|Error $error) {
                $cause = $error instanceof Error ? $error : $error->getPrevious();
                $violations[] = new Violation(
                    rule: 'parse_error', file: $path, value: 1, threshold: 0,
                    line: $cause instanceof Error ? max(1, $cause->getStartLine()) : null,
                    message: $error->getMessage(),
                );
            }
        }

        $project->resolveClasses();
        foreach ($checks as $check) {
            foreach ($check->checkProject($project) as $violation) {
                $violations[] = $violation;
            }
        }

        return new QualityResult($violations);
    }

    /**
     * Null requirements request every collector for a custom PHP check.
     *
     * @param list<Check> $checks
     *
     * @return list<string>|null
     */
    private function requiredAnalyses(array $checks): ?array
    {
        $required = [];
        foreach ($checks as $check) {
            $analyses = $check->requiredAnalyses();
            if ($analyses === null) {
                return null;
            }

            array_push($required, ...$analyses);
        }

        return array_values(array_unique($required));
    }
}
