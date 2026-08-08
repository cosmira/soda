<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\Engine\EvaluateInput;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\QualityEngine;
use Bunnivo\Soda\Quality\QualityResult;
use Bunnivo\Soda\Quality\Report\Violation;
use Bunnivo\Soda\Quality\Rule\LayerMixingChecker;
use PHPUnit\Framework\TestCase;

final class LayerMixingCheckerTest extends TestCase
{
    public function testReportsLayerMixingWhenNonPlainTypeDominatesDirectory(): void
    {
        $result = $this->evaluate(
            ['threshold' => 50, 'min_files' => 4],
            [
                'UserService',
                'UserService',
                'UserService',
                'UserService',
                'UserService',
                'Controller',
                'Controller',
                'Plain',
            ],
        );

        $this->assertCount(1, $result->violations);
        $violation = $result->violations->first();
        $this->assertSame('max_layer_dominance_percentage', $violation->rule);
        $this->assertInstanceOf(Violation::class, $violation);
        $this->assertSame(['value' => 63, 'threshold' => 50], $violation->limits());
        $this->assertStringContainsString('UserService dominates 62.5%', $violation->context->message ?? '');
        $this->assertStringContainsString('Controller=2, Plain=1', $violation->context->message ?? '');
    }

    public function testIgnoresPlainDominance(): void
    {
        $result = $this->evaluate(
            ['threshold' => 50, 'min_files' => 4],
            [
                'Plain',
                'Plain',
                'Plain',
                'Plain',
                'Plain',
                'Controller',
                'Controller',
                'UserService',
            ],
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresDirectoryBelowConfiguredMinFiles(): void
    {
        $result = $this->evaluate(
            ['threshold' => 50, 'min_files' => 5],
            [
                'UserService',
                'UserService',
                'Controller',
                'Plain',
            ],
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresPureDirectoryWithoutMixing(): void
    {
        $result = $this->evaluate(
            ['threshold' => 50, 'min_files' => 4],
            [
                'UserService',
                'UserService',
                'UserService',
                'UserService',
            ],
        );

        $this->assertCount(0, $result->violations);
    }

    /**
     * @param array{threshold: int, min_files: int} $ruleConfig
     * @param list<string>                          $fileTypes
     */
    private function evaluate(array $ruleConfig, array $fileTypes): QualityResult
    {
        $config = new QualityConfig(
            rules: ['max_layer_dominance_percentage' => $ruleConfig['threshold']],
            ruleState: new QualityConfigRuleState([], ['max_layer_dominance_percentage' => ['min_files' => $ruleConfig['min_files']]]),
        );
        $engine = new QualityEngine($config, [new LayerMixingChecker()]);

        return $engine->evaluate($this->createResult(count($fileTypes)), EvaluateInput::fromArrays(
            $this->metricsForDirectory($fileTypes),
        ));
    }

    private function createResult(int $classesOrTraits): Result
    {
        $loc = new LocMetrics([
            'directories'           => 1,
            'files'                 => $classesOrTraits,
            'linesOfCode'           => 100,
            'commentLinesOfCode'    => 0,
            'nonCommentLinesOfCode' => 100,
            'logicalLinesOfCode'    => 50,
        ]);
        $complexity = new ComplexityMetrics([
            'functions'       => 0,
            'funcLowest'      => 1,
            'funcAverage'     => 1.0,
            'funcHighest'     => 1,
            'classesOrTraits' => $classesOrTraits,
            'methods'         => 1,
            'methodLowest'    => 1,
            'methodAverage'   => 1.0,
            'methodHighest'   => 1,
        ]);

        return new Result([], new CoreMetrics($loc, $complexity));
    }

    /**
     * @param list<string> $fileTypes
     *
     * @return array<string, array<string, mixed>>
     */
    private function metricsForDirectory(array $fileTypes): array
    {
        $metrics = [];

        foreach ($fileTypes as $index => $type) {
            $classTypes = [
                'App\Services\File'.$index => $type,
            ];

            $metrics['/app/Services/File'.$index.'.php'] = [
                'file_loc'      => 20,
                'classes_count' => 1,
                'classes'       => [
                    'App\Services\File'.$index => [
                        'loc'               => 10,
                        'methods'           => 1,
                        'properties'        => 0,
                        'public_methods'    => 1,
                        'dependencies'      => 0,
                        'efferent_coupling' => 0,
                        'traits'            => 0,
                        'interfaces'        => 0,
                        'namespace'         => 'App\Services',
                        'namespace_depth'   => 2,
                    ],
                ],
                'classTypes' => $classTypes,
                'methods'    => [],
                'namespaces' => ['App\Services' => 1],
            ];
        }

        return $metrics;
    }
}
