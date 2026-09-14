<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\ComplexityVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ComplexityVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new ComplexityVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->complexity();
    }

    /**
     * @group enum-workaround
     * sebastian/complexity падает с AssertionError на Enum методах.
     */
    #[Group('enum-workaround')]
    public function testEnumMethodsDoNotCrashAndAreIncluded(): void
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

    public function fromValue(string $v): self {
        return match ($v) {
            'active' => self::Active,
            'inactive' => self::Inactive,
            default => throw new \InvalidArgumentException(),
        };
    }
}
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayHasKey('App\Status::label', $result);
        $this->assertSame(2, $result['App\Status::label']);

        $this->assertArrayHasKey('App\Status::fromValue', $result);
        $this->assertGreaterThanOrEqual(3, $result['App\Status::fromValue']);
    }

    /**
     * @group enum-workaround
     */
    #[Group('enum-workaround')]
    public function testEnumWithClassAndTraitStillWorks(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
enum E { case A; public function m() { return 1; } }
class C { public function m() { if (true) return 1; return 0; } }
trait T { public function m() { return 1; } }
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertArrayHasKey('App\E::m', $result);
        $this->assertArrayHasKey('App\C::m', $result);
        $this->assertArrayHasKey('App\T::m', $result);
    }

    public function testAnonymousClassMethodsHaveStableComplexityNameAndInterfacesAreSkipped(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
interface Contract { public function ignored(): void; }
$service = new class {
    public function run(): int
    {
        if (true) {
            return 1;
        }

        return 0;
    }
};
PHP;
        $result = $this->parseAndCollect($code);

        $this->assertSame(['anonymous class' => 2], $result);
    }
}
