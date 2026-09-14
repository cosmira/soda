<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CallableMetricsVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class CallableBooleanConditionsTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new CallableMetricsVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->booleanConditionsByMethod();
    }

    public function testSimpleCondition(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($a) {
        if ($a) {
            return 1;
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertCount(1, $result['App\Foo::bar']);
        $this->assertSame(1, $result['App\Foo::bar'][0]['count']);
    }

    public function testMultipleConditions(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($a, $b, $c, $d, $e) {
        if ($a && $b && ($c || $d) && !$e) {
            doSomething();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertCount(1, $result['App\Foo::bar']);
        $this->assertSame(5, $result['App\Foo::bar'][0]['count']);
    }

    public function testTernaryCondition(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($a, $b) {
        return $a && $b ? 1 : 0;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertCount(1, $result['App\Foo::bar']);
        $this->assertSame(2, $result['App\Foo::bar'][0]['count']);
    }

    public function testWhileCondition(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($a, $b, $c) {
        while ($a && $b && $c) {
            break;
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertSame(3, $result['App\Foo::bar'][0]['count']);
    }

    public function testClosureNotCounted(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($items) {
        return array_filter($items, function ($x) {
            return $x && $y && $z;
        });
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertCount(0, $result['App\Foo::bar']);
    }

    public function testTopLevelFunction(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
function process($a, $b) {
    if ($a || $b) {
        return true;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\process', $result);
        $this->assertSame(2, $result['App\process'][0]['count']);
    }

    public function testAnonymousClassConditionDoesNotLeakIntoOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function first(): object {
        return new class {
            public function ignored($a, $b, $c): void {
                if ($a && $b && $c) {}
            }
        };
    }

    public function second(): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame([], $result['App\Foo::first']);
        $this->assertSame([], $result['App\Foo::second']);
        $this->assertArrayNotHasKey('App\Foo::ignored', $result);
    }
}
