<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\SingleVisitorTraversal;
use Cosmira\Soda\Rules\Usage\ListOnlyArrayCollectingVisitor;
use Cosmira\Soda\Rules\Usage\ListOnlyArrayStrictness;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class ListOnlyArrayCollectingVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    private function lines(array $nodes, ListOnlyArrayStrictness $strictness = ListOnlyArrayStrictness::Pragmatic): array
    {
        $visitor = new ListOnlyArrayCollectingVisitor($strictness);
        SingleVisitorTraversal::traverse($nodes, $visitor);

        return $visitor->lines();
    }

    public function testPragmaticAllowsSingleStringKeyAccess(): void
    {
        $hits = $this->lines($this->parseSnippet('$x = $a["id"];'));

        $this->assertSame([], $hits);
    }

    public function testStrictReportsStringKeyAccess(): void
    {
        $hits = $this->lines($this->parseSnippet('$x = $a["id"];'), ListOnlyArrayStrictness::Strict);

        $this->assertNotEmpty($hits);
        $this->assertSame([1], $hits);
    }

    public function testReportsNestedArrayAccess(): void
    {
        $hits = $this->lines($this->parseSnippet('$x = $a[0][1];'));

        $this->assertNotEmpty($hits);
        $this->assertSame([1], $hits);
    }

    public function testPragmaticAllowsArrayShapeInDocblock(): void
    {
        $hits = $this->lines($this->parseSnippet(
            '/** @return array{id:int} */ function f(): array { return []; }',
        ));

        $this->assertSame([], $hits);
    }

    public function testStrictReportsArrayShapeInDocblock(): void
    {
        $hits = $this->lines($this->parseSnippet(
            '/** @return array{id:int} */ function f(): array { return []; }',
        ), ListOnlyArrayStrictness::Strict);

        $this->assertSame([1], $hits);
    }

    public function testReportsEachLineOnceInSourceOrder(): void
    {
        $hits = $this->lines($this->parseSnippet(<<<'PHP'
$x = $a['id']['name']; $y = $b['id'];
/** @return array{id: int} */
function f(): array { return $a['id']; }
PHP), ListOnlyArrayStrictness::Strict);

        $this->assertSame([1, 3], $hits);
    }

    public function testAllowsForeachWithoutDimFetch(): void
    {
        $hits = $this->lines($this->parseSnippet(
            'foreach ($users as $u) { echo $u; }',
        ));

        $this->assertSame([], $hits);
    }

    public function testAllowsListLiteralAssignment(): void
    {
        $hits = $this->lines($this->parseSnippet('$ids = [1, 2, 3];'));

        $this->assertSame([], $hits);
    }

    public function testAllowsVariableIndexSingleLevel(): void
    {
        $hits = $this->lines($this->parseSnippet('$x = $a[$i];'));

        $this->assertSame([], $hits);
    }
}
