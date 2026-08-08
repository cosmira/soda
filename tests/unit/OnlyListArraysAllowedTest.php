<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\ListOnlyArray\ListOnlyArrayStrictness;
use Bunnivo\Soda\Plugins\Rules\ListOnlyArray\OnlyListArraysAllowed;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\MethodMetricsData;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;

use function collect;

use PHPUnit\Framework\TestCase;

final class OnlyListArraysAllowedTest extends TestCase
{
    public function testPragmaticReportsNestedAccess(): void
    {
        $file = $this->tempFile("<?php\n\$x = \$row['a']['b'];\n");
        $rule = new OnlyListArraysAllowed;

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertSame('only_list_arrays', $violations->first()->rule);
        $this->assertStringContainsString('Nested array indexing', (string) $violations->first()->context->message);
        unlink($file);
    }

    public function testStrictReportsSingleStringKey(): void
    {
        $file = $this->tempFile("<?php\n\$x = \$row['a'];\n");
        $rule = new OnlyListArraysAllowed(strictness: ListOnlyArrayStrictness::Strict);

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Only list arrays are allowed', (string) $violations->first()->context->message);
        unlink($file);
    }

    public function testIgnoresPathsMatchingFormRequestPattern(): void
    {
        $dir = sys_get_temp_dir().'/soda-list-only-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/UpdateUserRequest.php';
        file_put_contents($file, "<?php\n\$x = \$row['a'];\n");

        $rule = new OnlyListArraysAllowed;

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
        rmdir($dir);
    }

    public function testIgnoresPathsUnderIgnorePathPrefixes(): void
    {
        $dir = sys_get_temp_dir().'/soda-list-prefix-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/Legacy.php';
        file_put_contents($file, "<?php\n\$x = \$row['a'];\n");

        $rule = new OnlyListArraysAllowed(ignorePathPrefixes: [$dir]);

        $violations = $rule->check($this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
        rmdir($dir);
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
        $path = tempnam(sys_get_temp_dir(), 'soda_list_only_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
