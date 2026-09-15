<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\CallableMetricsVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class CallableNestingTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new CallableMetricsVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->nestingByMethod();
    }

    public function testNoNestingReturnsZero(): void
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
        $this->assertSame(0, $result['App\Foo::bar']['depth']);
    }

    public function testSingleIfDepthOne(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar() {
        if (true) {
            return 1;
        }
    }

}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(1, $result['App\Foo::bar']['depth']);
    }

    public function testAlternativeBranchesShareTheirOwnersDepth(): void
    {
        foreach ([
            'if ($value) {} elseif ($other) {} else {}',
            'try {} catch (Exception $error) {} finally {}',
        ] as $body) {
            $result = $this->parseAndCollect('<?php function run($value, $other) { '.$body.' }');
            self::assertSame(1, $result['run']['depth'], $body);
        }
    }

    public function testWorkNestedInAnAlternativeBranchAddsExactlyOneLevel(): void
    {
        foreach ([
            'if ($value) {} elseif ($other) { if ($nested) {} }',
            'if ($value) {} else { if ($nested) {} }',
            'try {} catch (Exception $error) { if ($nested) {} }',
            'try {} finally { if ($nested) {} }',
        ] as $body) {
            $result = $this->parseAndCollect('<?php function run($value, $other, $nested) { '.$body.' }');
            self::assertSame(2, $result['run']['depth'], $body);
        }
    }

    public function testNestedForeachIfDepthTwo(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($users) {
        foreach ($users as $user) {
            if ($user->active) {
                process($user);
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(2, $result['App\Foo::bar']['depth']);
    }

    public function testDeepNestingExceedsLimit(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($users) {
        foreach ($users as $user) {
            if ($user->active) {
                foreach ($user->orders as $order) {
                    if ($order->paid) {
                        process($order);
                    }
                }
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(4, $result['App\Foo::bar']['depth']);
    }

    public function testClosureResetsContext(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($users) {
        return array_map(function ($u) {
            foreach ($u->items as $i) {
                if ($i->valid) {
                    return $i;
                }
            }
        }, $users);
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(0, $result['App\Foo::bar']['depth']);
    }

    public function testTryCatchAddsDepth(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar() {
        try {
            if (true) {
                throw new \Exception();
            }
        } catch (\Exception $e) {
            return 1;
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertGreaterThanOrEqual(2, $result['App\Foo::bar']['depth']);
    }

    public function testTopLevelFunction(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
function process($x) {
    if ($x) {
        return 1;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\process', $result);
        $this->assertSame(1, $result['App\process']['depth']);
    }

    public function testAnonymousClassNestingDoesNotLeakIntoOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function first(): object {
        return new class {
            public function ignored($a): void {
                if ($a) {
                    if ($a > 1) {}
                }
            }
        };
    }

    public function second(): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame(0, $result['App\Foo::first']['depth']);
        $this->assertSame(0, $result['App\Foo::second']['depth']);
        $this->assertArrayNotHasKey('App\Foo::ignored', $result);
    }

    /**
     * @group enum-workaround
     * Keep enum coverage when changing the complexity collector.
     */
    #[Group('enum-workaround')]
    public function testEnumWithMethodsTracksNesting(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
enum Status: string {
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string {
        if ($this === self::Active) {
            return 'Active';
        }
        return 'Inactive';
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Status::label', $result);
        $this->assertSame(1, $result['App\Status::label']['depth']);
    }

    /**
     * @group enum-workaround
     * Регрессия: endMethod без startMethod при Function_ с null resolveMethodName.
     * Проверка соседнего поведения в той же группе совместимости.
     */
    #[Group('enum-workaround')]
    public function testNestedFunctionDoesNotCrash(): void
    {
        $code = <<<'PHP'
<?php
function outer() {
    if (true) {
        function inner() {
            if (false) {
                return 1;
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('outer', $result);
        $this->assertArrayHasKey('inner', $result);
    }
}
