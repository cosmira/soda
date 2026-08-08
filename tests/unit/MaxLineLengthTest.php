<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Tests;

use Bunnivo\Soda\ComplexityMetrics;
use Bunnivo\Soda\CoreMetrics;
use Bunnivo\Soda\LocMetrics;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxLineLength;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Result;
use PHPUnit\Framework\TestCase;

final class MaxLineLengthTest extends TestCase
{
    public function testLineAtLimitPasses(): void
    {
        $file = $this->writeFixture(str_repeat('a', 100));

        try {
            $violations = (new MaxLineLength(100))->check($this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    public function testLineOverLimitReportsLineNumber(): void
    {
        $file = $this->writeFixture("short\n".str_repeat('b', 101));

        try {
            $violations = (new MaxLineLength(100))->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('max_line_length', $violations->first()->rule);
            $this->assertSame($file, $violations->first()->file);
            $this->assertSame(2, $violations->first()->line());
            $this->assertSame(['value' => 101, 'threshold' => 100], $violations->first()->limits());
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-line-length-');
        $this->assertIsString($file);
        file_put_contents($file, $contents);

        return $file;
    }

    private function context(string $file): EvaluationContext
    {
        return new EvaluationContext(
            QualityConfig::default(),
            new Result([], new CoreMetrics(
                new LocMetrics([
                    'directories'            => 0,
                    'files'                  => 0,
                    'linesOfCode'            => 0,
                    'commentLinesOfCode'     => 0,
                    'nonCommentLinesOfCode'  => 0,
                    'logicalLinesOfCode'     => 0,
                ]),
                new ComplexityMetrics([
                    'functions'       => 0,
                    'funcLowest'      => 0,
                    'funcAverage'     => 0.0,
                    'funcHighest'     => 0,
                    'classesOrTraits' => 0,
                    'methods'         => 0,
                    'methodLowest'    => 0,
                    'methodAverage'   => 0.0,
                    'methodHighest'   => 0,
                ]),
            )),
            new FileMetrics(new QualityCore([
                $file => [
                    'file_loc'      => 1,
                    'classes_count' => 0,
                    'classes'       => [],
                    'methods'       => [],
                    'namespaces'    => [],
                ],
            ], []), collect()),
        );
    }
}
