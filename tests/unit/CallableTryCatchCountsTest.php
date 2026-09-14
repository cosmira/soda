<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CallableMetricsVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class CallableTryCatchCountsTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new CallableMetricsVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->tryCatchCountsByMethod();
    }

    public function testThreeTryCatchInMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    function run() {
        try {} catch (\Exception $e) {}
        try {} catch (\Exception $e) {}
        try {} catch (\Exception $e) {}
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(3, $result['App\Foo::run']);
    }

    public function testTryCatchInsideClosureNotCountedForOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar() {
        $f = function () {
            try {} catch (\Throwable $e) {}
        };
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(0, $result['App\Foo::bar']);
    }

    public function testAnonymousClassTryCatchDoesNotLeakIntoOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function first(): object {
        return new class {
            public function ignored(): void {
                try {} catch (\Throwable $e) {}
            }
        };
    }

    public function second(): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame(0, $result['App\Foo::first']);
        $this->assertSame(0, $result['App\Foo::second']);
        $this->assertArrayNotHasKey('App\Foo::ignored', $result);
    }
}
