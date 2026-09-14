<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CallableMetricsVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class CallableReturnsTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new CallableMetricsVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->returnsByMethod();
    }

    public function testSingleReturn(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar() {
        return 1;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result);
        $this->assertSame(1, $result['App\Foo::bar']);
    }

    public function testMultipleReturns(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function calculate($value) {
        if ($value < 0) return 0;
        if ($value === 1) return 1;
        if ($value === 2) return 2;
        if ($value === 3) return 3;
        return 4;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::calculate', $result);
        $this->assertSame(5, $result['App\Foo::calculate']);
    }

    public function testClosureReturnsNotCounted(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($items) {
        return array_map(function ($x) {
            return $x * 2;
        }, $items);
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(1, $result['App\Foo::bar']);
    }

    public function testTopLevelFunction(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
function process($x) {
    if ($x) return 1;
    return 0;
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\process', $result);
        $this->assertSame(2, $result['App\process']);
    }

    public function testNoReturns(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar() {
        echo 'hello';
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(0, $result['App\Foo::bar']);
    }

    public function testAnonymousClassDoesNotDetachFollowingMethodsFromNamedClass(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function first(): object {
        return new class {
            public function ignored(): int {
                return 9;
            }
        };
    }

    public function second(): int {
        return 2;
    }
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame(1, $result['App\Foo::first']);
        $this->assertSame(1, $result['App\Foo::second']);
        $this->assertArrayNotHasKey('App\Foo::ignored', $result);
    }
}
