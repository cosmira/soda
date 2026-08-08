<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Tests;

use Bunnivo\Soda\ComplexityMetrics;
use Bunnivo\Soda\CoreMetrics;
use Bunnivo\Soda\LocMetrics;
use Bunnivo\Soda\Plugins\Rules\Naming\ClassNameLength;
use Bunnivo\Soda\Plugins\Rules\Naming\MethodNameLength;
use Bunnivo\Soda\Plugins\Rules\Naming\VariableNameLength;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Result;
use PHPUnit\Framework\TestCase;

final class NameLengthTest extends TestCase
{
    public function testVariableNameLengthReportsTooShortName(): void
    {
        $file = $this->writeFixture('<?php $x = 1; $good = 2;');

        try {
            $violations = (new VariableNameLength(min: 3, max: 16))->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('variable_name_length', $violations->first()->rule);
            $this->assertSame(1, $violations->first()->line());
        } finally {
            unlink($file);
        }
    }

    public function testMethodNameLengthReportsTooLongName(): void
    {
        $file = $this->writeFixture('<?php class User { public function methodNameIsTooLong(): void {} }');

        try {
            $violations = (new MethodNameLength(min: 3, max: 12))->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('method_name_length', $violations->first()->rule);
        } finally {
            unlink($file);
        }
    }

    public function testClassNameLengthReportsTooLongName(): void
    {
        $file = $this->writeFixture('<?php class VeryLongClassName {}');

        try {
            $violations = (new ClassNameLength(min: 3, max: 8))->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('class_name_length', $violations->first()->rule);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-name-length-');
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
