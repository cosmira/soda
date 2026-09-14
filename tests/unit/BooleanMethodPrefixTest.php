<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class BooleanMethodPrefixTest extends TestCase
{
    public function testReportsBooleanMethodWithoutExpectedPrefix(): void
    {
        $result = $this->evaluate(
            CheckFixture::checks(['boolean_methods_without_prefix' => 0], []),
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
            [new BooleanMethodPrefix(ignore: ['runningUnitTests'])],
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

    public function testViolationMessageUsesConfiguredPrefixes(): void
    {
        $config = [new BooleanMethodPrefix(prefix: ['may'])];

        $result = $this->evaluate(
            $config,
            $this->metricsForMethods([
                [
                    'name'                 => 'App\Application::running',
                    'methodName'           => 'running',
                    'class'                => 'App\Application',
                    'firstParamType'       => null,
                    'returnType'           => 'bool',
                    'line'                 => 10,
                    'isPublic'             => true,
                    'hasOverrideAttribute' => false,
                ],
                [
                    'name'                 => 'App\Application::mayRun',
                    'methodName'           => 'mayRun',
                    'class'                => 'App\Application',
                    'firstParamType'       => null,
                    'returnType'           => 'bool',
                    'line'                 => 14,
                    'isPublic'             => true,
                    'hasOverrideAttribute' => false,
                ],
            ], [[
                'name'     => 'App\Application',
                'kind'     => 'class',
                'line'     => 3,
                'inherits' => [],
                'methods'  => ['running', 'mayRun'],
            ]]),
        );

        $this->assertCount(1, $result->violations);
        $this->assertSame(
            'Boolean-returning method running() should start with is/has/can/should/does/was/are/do/did/will/try/may',
            $result->violations->first()->message,
        );
    }

    public function testReportsUnionBooleanReturnTypeWithoutExpectedPrefix(): void
    {
        $result = $this->evaluate(
            CheckFixture::checks(['boolean_methods_without_prefix' => 0], []),
            $this->metricsForMethods([[
                'name'                 => 'App\Application::running',
                'methodName'           => 'running',
                'class'                => 'App\Application',
                'firstParamType'       => null,
                'returnType'           => 'bool|null',
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
        $this->assertSame('App\Application::running', $result->violations->first()->method);
    }

    public function testIgnoresConfiguredQualifiedMethod(): void
    {
        $result = $this->evaluate(
            [new BooleanMethodPrefix(ignore: ['App\Application::runningUnitTests'])],
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
            CheckFixture::checks(['boolean_methods_without_prefix' => 0], []),
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
            CheckFixture::checks(['boolean_methods_without_prefix' => 0], []),
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
            CheckFixture::checks(['boolean_methods_without_prefix' => 0], []),
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
        $engine = CheckFixture::selected(CheckFixture::checks([], []), [new BooleanMethodPrefix()]);

        $result = new QualityResult(CheckFixture::runInput($engine, [$this->metricsForMethods([[
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
        ]]), []]));

        $this->assertCount(0, $result->violations);
    }

    private function evaluate(array $config, array $metrics): QualityResult
    {
        $engine = CheckFixture::selected($config, [null]);

        return new QualityResult(CheckFixture::runInput($engine, [$metrics, []]));
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
