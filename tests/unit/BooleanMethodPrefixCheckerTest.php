<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\Naming\BooleanMethodPrefix;
use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\Engine\EvaluateInput;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\QualityEngine;
use Bunnivo\Soda\Quality\QualityResult;
use Bunnivo\Soda\Quality\Rule\BooleanMethodPrefixChecker;
use PHPUnit\Framework\TestCase;

final class BooleanMethodPrefixCheckerTest extends TestCase
{
    public function testReportsBooleanMethodWithoutExpectedPrefix(): void
    {
        $result = $this->evaluate(
            new QualityConfig(['boolean_methods_without_prefix' => 0]),
            $this->metricsForMethods([[
                'name'                 => 'App\Application::running',
                'methodName'           => 'running',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 10,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['running'],
            ]]),
        );

        $this->assertCount(1, $result->violations);
        $this->assertSame('boolean_methods_without_prefix', $result->violations->first()->rule);
    }

    public function testIgnoresConfiguredMethodNameException(): void
    {
        $result = $this->evaluate(
            new QualityConfig(
                rules: ['boolean_methods_without_prefix' => 0],
                ruleState: new QualityConfigRuleState(['boolean_methods_without_prefix' => [
                    'files'   => [],
                    'classes' => [],
                    'methods' => ['runningUnitTests'],
                ]]),
            ),
            $this->metricsForMethods([[
                'name'                 => 'App\Application::runningUnitTests',
                'methodName'           => 'runningUnitTests',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 10,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['runningUnitTests'],
            ]]),
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresConfiguredClassException(): void
    {
        $result = $this->evaluate(
            new QualityConfig(
                rules: ['boolean_methods_without_prefix' => 0],
                ruleState: new QualityConfigRuleState(['boolean_methods_without_prefix' => [
                    'files'   => [],
                    'classes' => ['App\Application'],
                    'methods' => [],
                ]]),
            ),
            $this->metricsForMethods([[
                'name'                 => 'App\Application::runningUnitTests',
                'methodName'           => 'runningUnitTests',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 10,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['runningUnitTests'],
            ]]),
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresInheritedMethodFromParentClass(): void
    {
        $result = $this->evaluate(
            new QualityConfig(['boolean_methods_without_prefix' => 0]),
            $this->metricsForMethods([[
                'name'                 => 'App\ChildService::ready',
                'methodName'           => 'ready',
                'class'                => 'App\ChildService',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 14,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [
                [
                    'name'     => 'App\BaseService',
                    'kind'     => 'class',
                    'line'     => 3,
                    'inherits' => [],
                    'methods'  => ['ready'],
                ],
                [
                    'name'     => 'App\ChildService',
                    'kind'     => 'class',
                    'line'     => 10,
                    'inherits' => ['App\BaseService'],
                    'methods'  => ['ready'],
                ],
            ]),
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresInheritedMethodFromInterface(): void
    {
        $result = $this->evaluate(
            new QualityConfig(['boolean_methods_without_prefix' => 0]),
            $this->metricsForMethods([[
                'name'                 => 'App\HealthService::available',
                'methodName'           => 'available',
                'class'                => 'App\HealthService',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 14,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [
                [
                    'name'     => 'App\AvailabilityContract',
                    'kind'     => 'interface',
                    'line'     => 3,
                    'inherits' => [],
                    'methods'  => ['available'],
                ],
                [
                    'name'     => 'App\HealthService',
                    'kind'     => 'class',
                    'line'     => 10,
                    'inherits' => ['App\AvailabilityContract'],
                    'methods'  => ['available'],
                ],
            ]),
        );

        $this->assertCount(0, $result->violations);
    }

    public function testIgnoresOverrideAttributeAsExternalContractHint(): void
    {
        $result = $this->evaluate(
            new QualityConfig(['boolean_methods_without_prefix' => 0]),
            $this->metricsForMethods([[
                'name'                 => 'App\Application::runningUnitTests',
                'methodName'           => 'runningUnitTests',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 10,
                'isPublic'             => true,
                'hasOverrideAttribute' => true,
            ]], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['runningUnitTests'],
            ]]),
        );

        $this->assertCount(0, $result->violations);
    }

    public function testDefaultIgnoreListExemptsDeleteMethod(): void
    {
        $engine = new QualityEngine(new QualityConfig([]), [new BooleanMethodPrefix()]);

        $result = $engine->evaluate($this->createResult(), EvaluateInput::fromArrays(
            $this->metricsForMethods([[
                'name'                 => 'App\Application::delete',
                'methodName'           => 'delete',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool',
                'line'                 => 10,
                'isPublic'             => true,
                'hasOverrideAttribute' => false,
            ]], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['delete'],
            ]]),
        ));

        $this->assertCount(0, $result->violations);
    }

    private function evaluate(QualityConfig $config, array $metrics): QualityResult
    {
        $engine = new QualityEngine($config, [new BooleanMethodPrefixChecker()]);

        return $engine->evaluate($this->createResult(), EvaluateInput::fromArrays($metrics));
    }

    private function createResult(): Result
    {
        $loc = new LocMetrics([
            'directories'           => 1,
            'files'                 => 1,
            'linesOfCode'           => 20,
            'commentLinesOfCode'    => 0,
            'nonCommentLinesOfCode' => 20,
            'logicalLinesOfCode'    => 10,
        ]);
        $complexity = new ComplexityMetrics([
            'functions'       => 0,
            'funcLowest'      => 1,
            'funcAverage'     => 1.0,
            'funcHighest'     => 1,
            'classesOrTraits' => 1,
            'methods'         => 1,
            'methodLowest'    => 1,
            'methodAverage'   => 1.0,
            'methodHighest'   => 1,
        ]);

        return new Result([], new CoreMetrics($loc, $complexity));
    }

    /**
     * @param list<array{name: string, methodName: string, class: string, firstParamType: string|null, returnType: string, line: int, isPublic: bool, hasOverrideAttribute: bool}> $methods
     * @param list<array{name: string, kind: string, line: int, inherits: list<string>, methods: list<string>}>                                                                    $types
     *
     * @return array<string, array<string, mixed>>
     */
    private function metricsForMethods(array $methods, array $types): array
    {
        return [
            '/file.php' => [
                'file_loc'      => 20,
                'classes_count' => 1,
                'classes'       => [
                    'App\Application' => [
                        'loc'               => 10,
                        'methods'           => 1,
                        'properties'        => 0,
                        'public_methods'    => 1,
                        'dependencies'      => 0,
                        'efferent_coupling' => 0,
                        'traits'            => 0,
                        'interfaces'        => 0,
                        'namespace'         => 'App',
                        'namespace_depth'   => 1,
                    ],
                ],
                'methods'    => [],
                'namespaces' => ['App' => 1],
                'naming'     => [
                    'classes' => [['class' => 'App\Application', 'line' => 3]],
                    'methods' => $methods,
                    'types'   => $types,
                ],
            ],
        ];
    }
}
