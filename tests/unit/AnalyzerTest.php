<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\QualityResult;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    public function testAnalyzeReturnsQualityResult(): void
    {
        $path = __DIR__.'/../quality-fixture/SimpleClass.php';
        $result = Analyzer::analyze([$path], __DIR__.'/../quality-fixture/soda.php');

        $this->assertInstanceOf(QualityResult::class, $result);
        $this->assertTrue($result->violations->isEmpty());
    }

    public function testFluentFileBuilder(): void
    {
        $path = __DIR__.'/../quality-fixture/SimpleClass.php';
        $result = Analyzer::file($path)
            ->config(__DIR__.'/../quality-fixture/soda.php')
            ->analyse();

        $this->assertInstanceOf(QualityResult::class, $result);
    }
}
