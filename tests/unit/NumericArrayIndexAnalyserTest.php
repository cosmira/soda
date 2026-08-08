<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\Rules\NumericArrayIndex\NumericArrayIndexAnalyser;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class NumericArrayIndexAnalyserTest extends TestCase
{
    private NumericArrayIndexAnalyser $analyser;

    protected function setUp(): void
    {
        $this->analyser = new NumericArrayIndexAnalyser;
    }

    /** @return Node[] */
    private function parse(string $code): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php '.$code) ?? [];
    }

    /** @return list<array{line: int}> */
    private function hits(string $code): array
    {
        return $this->analyser->analyse($this->parse($code));
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
