<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Tests;

use Bunnivo\Soda\ComplexityMetrics;
use Bunnivo\Soda\CoreMetrics;
use Bunnivo\Soda\LocMetrics;
use Bunnivo\Soda\Plugins\Rules\Structural\MultilineMethodPhpDoc;
use Bunnivo\Soda\Plugins\Rules\Structural\MultilinePropertyPhpDoc;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Result;
use PHPUnit\Framework\TestCase;

final class MultilinePhpDocTest extends TestCase
{
    public function testPublicMethodRequiresMultilinePhpDocByDefault(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function missing(): void {}

    /** This is one line. */
    public function oneLine(): void {}

    // This is not PHPDoc.
    public function ordinaryComment(): void {}

    /**
     * Explain the method contract.
     */
    public function documented(): void {}
}
PHP);

        try {
            $violations = (new MultilineMethodPhpDoc())->check($this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame('multiline_method_phpdoc', $violations->first()->rule);
            $this->assertSame('missing', $violations->first()->method());
        } finally {
            unlink($file);
        }
    }

    public function testMethodVisibilityCanBeConfigured(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}
PHP);

        try {
            $violations = (new MultilineMethodPhpDoc(['protected', 'private']))->check($this->context($file));

            $this->assertCount(2, $violations);
            $this->assertSame(['protectedMethod', 'privateMethod'], $violations->map->method()->all());
        } finally {
            unlink($file);
        }
    }

    public function testInterfaceMethodsAreChecked(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
interface Contract
{
    public function run(): void;
}
PHP);

        try {
            $violations = (new MultilineMethodPhpDoc())->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('Contract', $violations->first()->class());
            $this->assertSame('run', $violations->first()->method());
        } finally {
            unlink($file);
        }
    }

    public function testPublicPropertyRequiresMultilinePhpDocByDefault(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public string $missing;

    /** This is one line. */
    public string $oneLine;

    // This is not PHPDoc.
    public string $ordinaryComment;

    /**
     * The configured API endpoint.
     */
    public string $documented;
}
PHP);

        try {
            $violations = (new MultilinePropertyPhpDoc())->check($this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame('multiline_property_phpdoc', $violations->first()->rule);
            $this->assertNull($violations->first()->method());
        } finally {
            unlink($file);
        }
    }

    public function testPropertyVisibilityCanBeConfigured(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public string $publicProperty;

    protected string $protectedProperty;

    private string $privateProperty;
}
PHP);

        try {
            $violations = (new MultilinePropertyPhpDoc(['private']))->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertStringContainsString('privateProperty', $violations->first()->context->message);
        } finally {
            unlink($file);
        }
    }

    public function testLegacyBudgetAllowsKnownViolations(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function missing(): void {}
}
PHP);

        try {
            $violations = (new MultilineMethodPhpDoc(maxViolations: 1))->check($this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-phpdoc-');
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
