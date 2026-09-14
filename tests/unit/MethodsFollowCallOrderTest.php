<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;
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
            $violations = CheckFixture::forRule(new MethodsFollowCallOrder(), $this->context($file));

            self::assertCount(0, $violations);
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
            $violations = CheckFixture::forRule(new MethodsFollowCallOrder(), $this->context($file));

            self::assertCount(1, $violations);
            self::assertSame('methods_follow_call_order', $violations->first()->rule);
            self::assertSame('SomeClass', $violations->first()->class);
            self::assertSame('method1', $violations->first()->method);
        } finally {
            unlink($file);
        }
    }

    public function testRuleReportsEveryOrderingViolationWithoutAllowance(): void
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
            $context = $this->context($file);
            $violations = CheckFixture::forRule(new MethodsFollowCallOrder(), $context);

            self::assertCount(1, $violations);
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
            $violations = CheckFixture::forRule(new MethodsFollowCallOrder(), $this->context($file));

            self::assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    public function testContradictoryLifecyclePairIsIgnored(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class Lifecycle
{
    public function start(): void
    {
        $this->changeState();
        $this->recordTransition();
    }

    public function stop(): void
    {
        $this->recordTransition();
        $this->changeState();
    }

    private function changeState(): void {}

    private function recordTransition(): void {}
}
PHP);

        try {
            self::assertCount(0, CheckFixture::forRule(new MethodsFollowCallOrder, $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    public function testThreeNodeCallOrderCycleIsIgnored(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class Lifecycle
{
    public function first(): void
    {
        $this->alpha();
        $this->beta();
    }

    public function second(): void
    {
        $this->beta();
        $this->gamma();
    }

    public function third(): void
    {
        $this->gamma();
        $this->alpha();
    }

    private function alpha(): void {}

    private function beta(): void {}

    private function gamma(): void {}
}
PHP);

        try {
            self::assertCount(0, CheckFixture::forRule(new MethodsFollowCallOrder, $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    public function testRepeatedConsistentOrderIsStillChecked(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class Workflow
{
    public function first(): void
    {
        $this->prepare();
        $this->commit();
    }

    public function second(): void
    {
        $this->prepare();
        $this->commit();
    }

    private function commit(): void {}

    private function prepare(): void {}
}
PHP);

        try {
            self::assertCount(2, CheckFixture::forRule(new MethodsFollowCallOrder, $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-method-order-');
        self::assertIsString($file);
        file_put_contents($file, $contents);

        return $file;
    }

    private function context(string $file): array
    {
        return [CheckFixture::checks(), [
            $file => [
                'file_loc'      => 1,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ]];
    }
}
