<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\NoUnusedMethods;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\MethodMetricsData;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;

use function collect;

use PHPUnit\Framework\TestCase;

final class NoUnusedMethodsTest extends TestCase
{
    public function testDefaultIgnoreSkipsPhpUnitLifecycle(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function setUp(): void {} }\n");
        $rule = new NoUnusedMethods;

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testExtraIgnoreMergedWithDefaults(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function seedDb(): void {} }\n");
        $rule = new NoUnusedMethods(ignore: ['seedDb']);

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testWithoutIgnoreReportsUnusedPrivate(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function dead(): void {} }\n");
        $rule = new NoUnusedMethods;

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertSame('unused_methods', $violations->first()->rule);
        unlink($file);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalMetrics(): array
    {
        return [
            'file_loc'       => 1,
            'classes_count'  => 0,
            'classes'        => [],
            'methods'        => [],
            'namespaces'     => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $qualityMetrics
     */
    private function context(array $qualityMetrics): EvaluationContext
    {
        $core = new QualityCore($qualityMetrics, []);
        $fileMetrics = new FileMetrics($core, collect(), new MethodMetricsData);

        $config = QualityConfig::default();
        $loc = new LocMetrics([
            'directories'        => 0, 'files' => 0, 'linesOfCode' => 0,
            'commentLinesOfCode' => 0, 'nonCommentLinesOfCode' => 0, 'logicalLinesOfCode' => 0,
        ]);
        $complexity = new ComplexityMetrics([
            'functions'       => 0, 'funcLowest' => 0, 'funcAverage' => 0.0, 'funcHighest' => 0,
            'classesOrTraits' => 0, 'methods' => 0, 'methodLowest' => 0, 'methodAverage' => 0.0, 'methodHighest' => 0,
        ]);
        $result = new Result([], new CoreMetrics($loc, $complexity));

        return new EvaluationContext($config, $result, $fileMetrics);
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_unused_methods_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
