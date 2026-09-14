<?php

declare(strict_types=1);
/*
 * This file is part of Soda.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\StructureVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class StructureVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code, int $fileLines = 50): array
    {
        $visitor = new StructureVisitor($fileLines);
        $this->traversePhpFile($code, $visitor);

        return $visitor->metrics();
    }

    public function testUsesProvidedFileLogicalLines(): void
    {
        $result = $this->parseAndCollect('<?php namespace App; class Foo {}', 123);

        $this->assertSame(123, $result['file_loc']);
    }

    public function testCollectsPropertiesPerClass(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    private $a;
    protected $b, $c;
    public $d;
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo', $result['classes']);
        $this->assertSame(4, $result['classes']['App\Foo']['properties']);
    }

    public function testCollectsPublicMethods(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function a() {}
    public function b() {}
    private function c() {}
    protected function d() {}
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(2, $result['classes']['App\Foo']['public_methods']);
    }

    public function testCollectsConstructorDependencies(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function __construct($a, $b, $c, $d) {}
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(4, $result['classes']['App\Foo']['dependencies']);
    }

    public function testCollectsTraitsPerClass(): void
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
        $this->assertSame(2, $result['classes']['App\Foo']['traits']);
    }

    public function testCollectsTraitMethodsAsClassLikeMetrics(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
trait LogsActivity {
    public function log(string $message): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayHasKey('App\LogsActivity', $result['classes']);
        $this->assertArrayHasKey('App\LogsActivity::log', $result['methods']);
        $this->assertSame(1, $result['classes']['App\LogsActivity']['methods']);
        $this->assertSame(1, $result['methods']['App\LogsActivity::log']['args']);
        $this->assertSame('trait', $result['classes']['App\LogsActivity']['kind']);
    }

    public function testCollectsInterfacesPerClass(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
interface I1 {}
interface I2 {}
interface I3 {}
class Foo implements I1, I2, I3 {}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(3, $result['classes']['App\Foo']['interfaces']);
    }

    public function testCollectsNamespaceDepth(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Services\User\Internal;
class Foo {}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame('App\Services\User\Internal', $result['classes']['App\Services\User\Internal\Foo']['namespace']);
        $this->assertSame(4, $result['classes']['App\Services\User\Internal\Foo']['namespace_depth']);
    }

    public function testCollectsClassesPerFile(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class A {}
class B {}
trait C {}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame(3, $result['classes_count']);
    }

    public function testCollectsMethodLocAndArgs(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function bar($a, $b, $c) {
        $x = 1;
        $y = 2;
        return $x + $y;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertArrayHasKey('App\Foo::bar', $result['methods']);
        $this->assertSame(3, $result['methods']['App\Foo::bar']['args']);
        $this->assertGreaterThanOrEqual(5, $result['methods']['App\Foo::bar']['loc']);
    }

    public function testCollectsNamedFunctionLocAndArgs(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Support;

function normalize($value, $fallback) {
    $value = trim($value);

    return $value !== '' ? $value : $fallback;
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayHasKey('App\Support\normalize', $result['methods']);
        $this->assertSame(2, $result['methods']['App\Support\normalize']['args']);
        $this->assertGreaterThanOrEqual(5, $result['methods']['App\Support\normalize']['loc']);
    }

    public function testSkipsInterfaceMethodsFromMethodMetrics(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
interface Contract {
    public function run(string $value): void;
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame([], $result['methods']);
        $this->assertSame(0, $result['classes_count']);
    }

    public function testSkipsAbstractMethodsFromMethodMetrics(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
abstract class BaseService {
    abstract public function contract(string $value): void;

    public function run(): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayNotHasKey('App\BaseService::contract', $result['methods']);
        $this->assertArrayHasKey('App\BaseService::run', $result['methods']);
        $this->assertSame(1, $result['classes']['App\BaseService']['methods']);
    }

    public function testAnonymousClassDoesNotDetachFollowingMethodsFromNamedClass(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Foo {
    public function first(): object
    {
        return new class {
            public function ignored(): void {}
        };
    }

    public function second(): void {}
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayHasKey('App\Foo::first', $result['methods']);
        $this->assertArrayHasKey('App\Foo::second', $result['methods']);
        $this->assertArrayNotHasKey('unknown::second', $result['methods']);
        $this->assertSame(2, $result['classes']['App\Foo']['methods']);
    }

    public function testStoresInheritanceOnTheClassDeclaration(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Services;

interface Runs {}
interface Other {}
class BaseService {}

class UsesParent extends BaseService {}
class UsesInterface implements Runs, Other {}
class PlainClass {}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame('App\Services\BaseService', $result['classes']['App\Services\UsesParent']['parent']);
        $this->assertSame(['App\Services\Runs', 'App\Services\Other'], $result['classes']['App\Services\UsesInterface']['interface_names']);
        $this->assertSame('class', $result['classes']['App\Services\PlainClass']['kind']);
        $this->assertArrayNotHasKey('parent', $result['classes']['App\Services\PlainClass']);
    }
}
