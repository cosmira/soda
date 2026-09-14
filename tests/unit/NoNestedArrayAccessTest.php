<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Usage\NoNestedArrayAccess;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class NoNestedArrayAccessTest extends TestCase
{
    use ParsesPhpSnippets;

    /** @return list<Violation> */
    private function hits(string $code, int $maxDepth = 1): array
    {
        return iterator_to_array((new NoNestedArrayAccess($maxDepth))->checkFile(new FileFacts('fixture.php', '<?php '.$code, $this->parseSnippet($code), [])));
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
