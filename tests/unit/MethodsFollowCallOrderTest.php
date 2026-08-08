<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Tests;

use Bunnivo\Soda\ComplexityMetrics;
use Bunnivo\Soda\CoreMetrics;
use Bunnivo\Soda\LocMetrics;
use Bunnivo\Soda\Plugins\Rules\Structural\MethodsFollowCallOrder;
use Bunnivo\Soda\Quality\EvaluationContext;
use Bunnivo\Soda\Quality\EvaluationContext\FileMetrics;
use Bunnivo\Soda\Quality\EvaluationContext\QualityCore;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Result;
use PHPUnit\Framework\TestCase;

final class MethodsFollowCallOrderTest extends TestCase
{
    public function testMethodsInCallOrderPass(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function someMethod(): void
    {
        $this->method1();
        $this->method2();
    }

    private function method1(): void
    {
        $this->method11();
    }

    private function method11(): void {}

    private function method2(): void {}
}
PHP);

        try {
            $violations = (new MethodsFollowCallOrder())->check($this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    public function testMethodDeclaredBeforeEarlierCallIsReported(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function someMethod(): void
    {
        $this->method1();
        $this->method2();
    }

    private function method2(): void {}

    private function method1(): void {}
}
PHP);

        try {
            $violations = (new MethodsFollowCallOrder())->check($this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('methods_follow_call_order', $violations->first()->rule);
            $this->assertSame('SomeClass', $violations->first()->class());
            $this->assertSame('method1', $violations->first()->method());
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
    public function someMethod(): void
    {
        $this->method1();
        $this->method2();
    }

    private function method2(): void {}

    private function method1(): void {}
}
PHP);

        try {
            $violations = (new MethodsFollowCallOrder(maxViolations: 1))->check($this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    public function testExternalCallsAndInterfacesAreIgnored(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
interface Contract
{
    public function run(): void;
}

final class SomeClass
{
    public function run(Other $other): void
    {
        $other->method2();
        $this->method1();
    }

    private function method1(): void {}
}
PHP);

        try {
            $violations = (new MethodsFollowCallOrder())->check($this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-method-order-');
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
