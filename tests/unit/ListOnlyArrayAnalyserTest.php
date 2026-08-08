<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\ListOnlyArray\ListOnlyArrayAnalyser;
use Bunnivo\Soda\Plugins\Rules\ListOnlyArray\ListOnlyArrayIssue;
use Bunnivo\Soda\Plugins\Rules\ListOnlyArray\ListOnlyArrayStrictness;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ListOnlyArrayAnalyserTest extends TestCase
{
    private ListOnlyArrayAnalyser $analyser;

    protected function setUp(): void
    {
        $this->analyser = new ListOnlyArrayAnalyser;
    }

    /** @return Node[] */
    private function parse(string $code): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php '.$code) ?? [];
    }

    public function testPragmaticAllowsSingleStringKeyAccess(): void
    {
        $hits = $this->analyser->analyse($this->parse('$x = $a["id"];'));

        $this->assertSame([], $hits);
    }

    public function testStrictReportsStringKeyAccess(): void
    {
        $strict = new ListOnlyArrayAnalyser(ListOnlyArrayStrictness::Strict);
        $hits = $strict->analyse($this->parse('$x = $a["id"];'));

        $this->assertNotEmpty($hits);
        $this->assertSame(ListOnlyArrayIssue::StringKey->value, $hits[0]['issue']);
    }

    public function testReportsNestedArrayAccess(): void
    {
        $hits = $this->analyser->analyse($this->parse('$x = $a[0][1];'));

        $this->assertNotEmpty($hits);
        $this->assertSame(ListOnlyArrayIssue::NestedAccess->value, $hits[0]['issue']);
    }

    public function testPragmaticAllowsArrayShapeInDocblock(): void
    {
        $hits = $this->analyser->analyse($this->parse(
            '/** @return array{id:int} */ function f(): array { return []; }',
        ));

        $this->assertSame([], $hits);
    }

    public function testStrictReportsArrayShapeInDocblock(): void
    {
        $strict = new ListOnlyArrayAnalyser(ListOnlyArrayStrictness::Strict);
        $hits = $strict->analyse($this->parse(
            '/** @return array{id:int} */ function f(): array { return []; }',
        ));

        $this->assertNotEmpty($hits);
        $this->assertSame(ListOnlyArrayIssue::PhpDocShape->value, $hits[0]['issue']);
    }

    public function testAllowsForeachWithoutDimFetch(): void
    {
        $hits = $this->analyser->analyse($this->parse(
            'foreach ($users as $u) { echo $u; }',
        ));

        $this->assertSame([], $hits);
    }

    public function testAllowsListLiteralAssignment(): void
    {
        $hits = $this->analyser->analyse($this->parse('$ids = [1, 2, 3];'));

        $this->assertSame([], $hits);
    }

    public function testAllowsVariableIndexSingleLevel(): void
    {
        $hits = $this->analyser->analyse($this->parse('$x = $a[$i];'));

        $this->assertSame([], $hits);
    }
}
