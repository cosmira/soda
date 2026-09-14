<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\EfferentCouplingVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class EfferentCouplingVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    /**
     * @psalm-return array<string, int>
     */
    private function parseAndCollect(string $code): array
    {
        $visitor = new EfferentCouplingVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->couplingCountsByClass();
    }

    public function testCountsDistinctExternalTypes(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class A {}
class B {}
class C {}
class D {}
class E { public static function m(): void {} }
interface I {}
trait T {}
class Foo extends A implements I {
    use T;
    private B $injected;
    public function x(B $b): C {
        return new D();
    }
    public static function y(): void {
        E::m();
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(7, $result['App\Foo']);
    }

    public function testSelfReturnNotCountedAsExternal(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function chain(): self {
        return $this;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(0, $result['App\Foo']);
    }

    public function testTraitListsUsedTraits(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
trait T1 {}
trait T2 {}
class Foo {
    use T1, T2;
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(2, $result['App\Foo']);
    }

    public function testAnonymousClassBodyDoesNotInflateOuterClassCoupling(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function factory(): object {
        return new class extends ExternalBase implements ExternalContract {
            private HiddenDependency $hidden;

            public function ignored(InnerParameter $parameter): InnerReturn {
                return new InnerReturn($parameter);
            }
        };
    }
}
PHP;

        $result = $this->parseAndCollect($code);

        $this->assertSame(2, $result['App\Foo']);
    }
}
