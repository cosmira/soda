<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\NestedArrayAccess\NestedArrayAccessAnalyser;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NestedArrayAccessAnalyserTest extends TestCase
{
    /** @return Node[] */
    private function parse(string $code): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php '.$code) ?? [];
    }

    /** @return list<array{line: int}> */
    private function hits(string $code, int $maxDepth = 1): array
    {
        return (new NestedArrayAccessAnalyser($maxDepth))->analyse($this->parse($code));
    }

    public function testReportsTwoLevelAccess(): void
    {
        $code = <<<'PHP'
$x = $usage[$c]['called'];
$y = $data['a']['b'];
PHP;

        $hits = $this->hits($code);
        $this->assertCount(2, $hits);
        $this->assertSame(2, count(array_unique(array_column($hits, 'line'))));
    }

    public function testReportsThreeLevels(): void
    {
        $code = <<<'PHP'
$z = $a['b']['c']['d'];
PHP;

        $this->assertNotSame([], $this->hits($code));
    }

    public function testReportsMixedNumericAndString(): void
    {
        $code = <<<'PHP'
$u = $array[0]['key'];
$v = $array['key'][0];
PHP;

        $this->assertCount(2, $this->hits($code));
    }

    public function testAllowsSingleLevel(): void
    {
        $code = <<<'PHP'
$a = $usage[$c];
$b = $array['key'];
$c = $this->usage;
PHP;

        $this->assertSame([], $this->hits($code));
    }

    public function testRespectsMaxDepthTwo(): void
    {
        $code = '$x = $a["b"]["c"];';

        $this->assertSame([], $this->hits($code, 2));
    }

    public function testReportsNestedInsideIsset(): void
    {
        $code = 'isset($a["b"]["c"]);';

        $this->assertNotSame([], $this->hits($code));
    }

    public function testReportsNestedInsideNullCoalesce(): void
    {
        $code = '$x = $a["b"]["c"] ?? null;';

        $this->assertNotSame([], $this->hits($code));
    }
}
