<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSharedHelpersAreNotReordered(): void
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
            self::assertCount(0, CheckFixture::forRule(new MethodsFollowCallOrder, $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    #[DataProvider('scopeCases')]
    public function testLocalSequenceScope(string $body, string $helpers, int $count): void
    {
        $file = $this->writeFixture('<?php class Workflow { public function run() { '.$body.' } '.$helpers.' }');

        try {
            self::assertCount($count, CheckFixture::forRule(new MethodsFollowCallOrder, $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    public static function scopeCases(): iterable
    {
        $private = 'private function commit() {} private function prepare() {}';
        yield 'private direct' => ['$this->prepare(); $this->commit();', $private, 1];
        yield 'case insensitive' => ['$this->PREPARE(); $this->COMMIT();', $private, 1];
        yield 'self static' => ['self::PREPARE(); self::commit();', 'private static function commit() {} private static function prepare() {}', 1];
        yield 'public methods' => ['$this->prepare(); $this->commit();', 'public function commit() {} public function prepare() {}', 0];
        yield 'protected hooks' => ['$this->prepare(); $this->commit();', 'protected function commit() {} protected function prepare() {}', 0];
        yield 'branch' => ['if (ready()) { $this->prepare(); } $this->commit();', $private, 0];
        yield 'loop' => ['while (ready()) { $this->prepare(); $this->commit(); }', $private, 0];
        yield 'deferred closure' => ['$callback = function () { $this->prepare(); $this->commit(); };', $private, 0];
        yield 'arrow' => ['$callback = fn () => $this->prepare(); $this->commit();', $private, 0];
        yield 'nested class' => ['$value = new class { function inner() { $this->prepare(); $this->commit(); } };', $private, 0];
        yield 'shared callback helper' => ['$this->prepare(); $this->commit(); $callback = fn () => $this->prepare();', $private, 0];
        yield 'first class callable' => ['$this->prepare(...); $this->commit();', $private, 0];
        yield 'nested evaluation order' => ['$this->commit($this->prepare());', $private, 0];
        yield 'return branch' => ['$this->prepare(); return $this->commit();', $private, 0];
        yield 'callback array' => ['$this->prepare(); $this->commit(); register([$this, "prepare"]);', $private, 0];
        yield 'receiver alias' => ['$this->prepare(); $this->commit(); $alias = $this; $alias->prepare();', $private, 0];
        yield 'late static use' => ['$this->prepare(); $this->commit(); static::prepare();', $private, 0];
        yield 'contradictory sequence' => ['$this->prepare(); $this->commit(); $this->prepare();', $private, 0];
        yield 'dynamic local call' => ['$this->prepare(); $this->commit(); $this->$action();', $private, 0];
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
