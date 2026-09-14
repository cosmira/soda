<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\QualityResult;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Structure\MaxLayerDominancePercentage;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxLayerDominancePercentageTest extends TestCase
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
        $this->assertSame(['value' => 63, 'threshold' => 50], ['value' => $violation->value, 'threshold' => $violation->threshold]);
        $this->assertStringContainsString('Service suffix appears in 62.5%', $violation->message ?? '');
        $this->assertStringContainsString('Controller=2, Plain=1', $violation->message ?? '');
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

    public function testIgnoresOneFamilyWithPlainHelpers(): void
    {
        $result = $this->evaluate(
            ['threshold' => 50, 'min_files' => 4],
            ['Parser', 'Parser', 'Parser', 'Plain', 'Plain'],
        );

        self::assertCount(0, $result->violations);
    }

    public function testDoesNotInferArchitecturalLayersFromInheritance(): void
    {
        $result = $this->analyseClasses([
            'Contract.php' => 'abstract class Contract {}',
            'Base.php'     => 'abstract class Base extends Contract {}',
            'Alpha.php'    => 'class Alpha extends Base {}',
            'Beta.php'     => 'class Beta extends Base {}',
            'Gamma.php'    => 'class Gamma extends Base {}',
            'Helper.php'   => 'class Helper {}',
        ]);

        self::assertCount(0, $result->violations);
    }

    public function testReportsMixedExplicitRolesInAFeatureDirectory(): void
    {
        $result = $this->analyseClasses([
            'Contract.php'           => 'abstract class Contract {}',
            'BaseService.php'        => 'abstract class BaseService extends Contract {}',
            'AlphaService.php'       => 'class AlphaService extends BaseService {}',
            'BetaService.php'        => 'class BetaService extends BaseService {}',
            'GammaService.php'       => 'class GammaService extends BaseService {}',
            'EndpointController.php' => 'class EndpointController extends ExternalController {}',
        ]);

        self::assertCount(1, $result->violations);
        $violation = $result->violations->first();
        self::assertSame(67, $violation->value);
        self::assertSame(50, $violation->threshold);
        self::assertStringContainsString('Service suffix appears in 66.7%', $violation->message);
        self::assertStringContainsString('Controller=1', $violation->message);
    }

    public function testTraitsAndInterfacesDoNotBecomeClassRoles(): void
    {
        $result = $this->analyseClasses([
            'FirstService.php'       => 'trait FirstService {}',
            'SecondService.php'      => 'trait SecondService {}',
            'ThirdService.php'       => 'interface ThirdService {}',
            'EndpointController.php' => 'class EndpointController {}',
        ]);

        self::assertCount(0, $result->violations);
    }

    public function testInheritanceCyclesTerminate(): void
    {
        $result = $this->analyseClasses([
            'Alpha.php'  => 'class Alpha extends Beta {}',
            'Beta.php'   => 'class Beta extends Alpha {}',
            'Gamma.php'  => 'class Gamma extends Alpha {}',
            'Helper.php' => 'class Helper {}',
        ]);

        self::assertCount(0, $result->violations);
    }

    private function analyseClasses(array $sources): QualityResult
    {
        $directory = sys_get_temp_dir().'/soda-family-'.uniqid();
        mkdir($directory, 0700);
        $files = [];
        foreach ($sources as $name => $source) {
            $path = $directory.'/'.$name;
            file_put_contents($path, '<?php namespace Example; '.$source);
            $files[] = $path;
        }

        try {
            return (new Runner)->check(
                $files,
                Soda::configure()->with([
                    new MaxLayerDominancePercentage(50, 4),
                ]),
            );
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /**
     * @param array{threshold: int, min_files: int} $ruleConfig
     * @param list<string>                          $fileTypes
     */
    private function evaluate(array $ruleConfig, array $fileTypes): QualityResult
    {
        $config = CheckFixture::checks(['max_layer_dominance_percentage' => $ruleConfig['threshold']], ['exceptions' => [], 'options' => ['max_layer_dominance_percentage' => ['min_files' => $ruleConfig['min_files']]]]);
        $engine = CheckFixture::selected($config, [null]);

        return new QualityResult(CheckFixture::runInput($engine, [$this->metricsForDirectory($fileTypes), []]));
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
            $metrics['/app/Feature/File'.$index.'.php'] = [
                'file_loc'      => 20,
                'classes_count' => 1,
                'classes'       => [
                    'App\Services\File'.$index.$type => [
                        'kind'              => 'class',
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
                'methods'    => [],
                'namespaces' => ['App\Services' => 1],
            ];
        }

        return $metrics;
    }
}
