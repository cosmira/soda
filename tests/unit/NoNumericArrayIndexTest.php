<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class NoNumericArrayIndexTest extends TestCase
{
    use ParsesPhpSnippets;

    /** @return list<Violation> */
    private function hits(string $code): array
    {
        return iterator_to_array((new NoNumericArrayIndex)->checkFile(new FileFacts('fixture.php', '<?php '.$code, $this->parseSnippet($code), [])));
    }

    public function testReportsLiteralAndNegativeIndices(): void
    {
        $code = <<<'PHP'
$a = [];
$x = $a[0];
$y = $a[-1];
isset($a[2]);
$z = $a[3] ?? null;
PHP;

        $lines = array_column($this->hits($code), 'line');
        $this->assertSame([2, 3, 4, 5], $lines);
    }

    public function testAllowsStringKeyAndVariableIndex(): void
    {
        $code = <<<'PHP'
$a = ['k' => 1];
$b = $a['k'];
function f(array $x, mixed $i): mixed {
    return $x[$i];
}
PHP;

        $this->assertSame([], $this->hits($code));
    }

    public function testIgnoresAppendBracket(): void
    {
        $code = '$a = []; $a[] = 1;';

        $this->assertSame([], $this->hits($code));
    }
}
