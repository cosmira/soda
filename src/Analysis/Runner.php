<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Contracts\ResearchCheck;
use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Error;

class Runner
{
    /**
     * Supply the PHP fact collector used for this analysis run.
     *
     * @param FactCollector $collector Collects file and project facts requested by the selected checks.
     */
    public function __construct(private readonly FactCollector $collector = new FactCollector()) {}

    /**
     * Execute configured rules over each parsed file, then the scalar project facts.
     *
     * @param list<non-empty-string> $files
     * @param list<Check>            $checks
     */
    public function check(array $files, array $checks): QualityResult
    {
        $required = $this->requiredAnalyses($checks);
        $project = new ProjectFacts();
        $violations = $this->analyseFiles($files, $checks, $required, $project);

        [$projectViolations, $research] = $this->analyseProject($checks, $project);
        array_push($violations, ...$projectViolations);

        return new QualityResult($violations, $research);
    }

    /**
     * Parse every requested file, run the file checks and accumulate shared project facts.
     *
     * @param list<non-empty-string> $files
     * @param list<Check>            $checks
     * @param list<string>           $required
     *
     * @return list<Violation>
     */
    private function analyseFiles(array $files, array $checks, array $required, ProjectFacts $project): array
    {
        $violations = [];
        foreach (array_unique($files) as $path) {
            array_push($violations, ...$this->analyseFile($path, $checks, $required, $project));
        }

        return $violations;
    }

    /**
     * Analyze one file and record its facts, or report an unparseable source instead.
     *
     * @param list<Check>  $checks
     * @param list<string> $required
     *
     * @return list<Violation>
     */
    private function analyseFile(string $path, array $checks, array $required, ProjectFacts $project): array
    {
        try {
            $file = $this->collector->collect($path, $required);
            $violations = [];
            foreach ($checks as $check) {
                if ($check->isExcludedPath($path)) {
                    continue;
                }

                array_push($violations, ...$check->checkFile($file));
            }

            $project->add($file);

            return $violations;
        } catch (ParserException|Error $error) {
            return [$this->parseErrorViolation($path, $error)];
        }
    }

    /**
     * Build the diagnostic raised when a file cannot be parsed at all.
     */
    private function parseErrorViolation(string $path, ParserException|Error $error): Violation
    {
        $cause = $error instanceof Error ? $error : $error->getPrevious();

        return new Violation(
            rule: 'parse_error', file: $path, value: 1, threshold: 0,
            line: $cause instanceof Error ? max(1, $cause->getStartLine()) : null,
            message: $error->getMessage(),
        );
    }

    /**
     * Resolve shared classes, then run project checks and collect opt-in research reports.
     *
     * @param list<Check> $checks
     *
     * @return array{0: list<Violation>, 1: array<string, mixed>}
     */
    private function analyseProject(array $checks, ProjectFacts $project): array
    {
        $project->resolveClasses();
        $violations = [];
        $research = [];
        foreach ($checks as $check) {
            $facts = $this->projectFor($check, $project);
            if ($check instanceof ResearchCheck) {
                $research[$check->id()] = $check->researchProject($facts);
            }

            foreach ($check->checkProject($facts) as $violation) {
                $violations[] = $violation;
            }
        }

        return [$violations, $research];
    }

    /**
     * Restrict one rule's source files after resolving the complete inherited class surface.
     */
    private function projectFor(Check $check, ProjectFacts $project): ProjectFacts
    {
        $filtered = clone $project;
        $filtered->files = array_filter(
            $project->files,
            fn (string $path): bool => ! $check->isExcludedPath($path),
            ARRAY_FILTER_USE_KEY,
        );

        return $filtered;
    }

    /**
     * Combine the explicitly requested collector groups without running unrelated analyses.
     *
     * @param list<Check> $checks
     *
     * @return list<string>
     */
    private function requiredAnalyses(array $checks): array
    {
        $required = [];
        foreach ($checks as $check) {
            $analyses = $check->requiredAnalyses();
            array_push($required, ...$analyses);
        }

        return array_values(array_unique($required));
    }
}
