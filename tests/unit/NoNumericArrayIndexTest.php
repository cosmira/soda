<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoNumericArrayIndexTest extends TestCase
{
    use ParsesPhpSnippets;

    /** @return list<Violation> */
    private function hits(string $code): array
    {
        return iterator_to_array((new NoNumericArrayIndex)->checkFile(new FileFacts('fixture.php', '<?php '.$code, $this->parseSnippet($code), [])));
    }

    #[DataProvider('cases')]
    public function testTupleEvidence(string $code, int $count): void
    {
        $hits = $this->hits($code);
        self::assertCount($count, $hits);
        if ($count > 0) {
            self::assertSame('no_numeric_array_index', $hits[0]->rule);
            self::assertGreaterThan(0, $hits[0]->line);
            self::assertStringContainsString('Review positional fields', $hits[0]->message);
        }
    }

    public static function cases(): iterable
    {
        $doc = '/** @param array{string, int} $row */ ';
        yield 'implicit tuple' => [$doc.'function render(array $row) { return $row[0].$row[1]; }', 1];
        yield 'explicit tuple' => ['/** @param array{0: string, 1: int} $row */ function render(array $row) { return $row[1].$row[0]; }', 1];
        yield 'method' => ['class View { '.$doc.'public function render(array $row) { return $row[0].$row[1]; } }', 1];
        yield 'list' => ['/** @param list<string> $row */ function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'unknown' => ['function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'literal' => ['function render() { $row = ["hello", 1]; return $row[0].$row[1]; }', 0];
        yield 'negative key' => ['function render() { $row = [-1 => "hello"]; return $row[-1]; }', 0];
        yield 'unreachable second read' => [$doc.'function render(array $row) { return $row[0]; return $row[1]; }', 0];
        yield 'duplicate annotation' => ['/** @param array{string, int} $row @param array{int, string} $row */ function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'one position' => [$doc.'function render(array $row) { return $row[0].$row[0]; }', 0];
        yield 'reassignment' => [$doc.'function render(array $row) { $row = load(); return $row[0].$row[1]; }', 0];
        yield 'element write' => [$doc.'function render(array $row) { $row[0] = "new"; return $row[0].$row[1]; }', 0];
        yield 'reference parameter' => [$doc.'function render(array &$row) { return $row[0].$row[1]; }', 0];
        yield 'reference alias' => [$doc.'function render(array $row) { $alias =& $row; return $row[0].$row[1]; }', 0];
        yield 'escape to function' => [$doc.'function render(array $row) { mutate($row); return $row[0].$row[1]; }', 0];
        yield 'element reference escape' => [$doc.'function render(array $row) { mutate($row[0]); return $row[0].$row[1]; }', 0];
        yield 'short circuit' => [$doc.'function render(array $row) { return ready() && ($row[0].$row[1]); }', 0];
        yield 'dynamic variable' => [$doc.'function render(array $row, $key) { $$key = []; return $row[0].$row[1]; }', 0];
        yield 'branch' => [$doc.'function render(array $row) { if (ready()) { return $row[0].$row[1]; } }', 0];
        yield 'loop' => [$doc.'function render(array $row) { while (ready()) { echo $row[0].$row[1]; } }', 0];
        yield 'closure capture' => [$doc.'function render(array $row) { return fn () => $row[0].$row[1]; }', 0];
        yield 'nested class' => [$doc.'function render(array $row) { return new class { function render($row) { return $row[0].$row[1]; } }; }', 0];
        yield 'different callables' => [$doc.'function first(array $row) { return $row[0]; } '.$doc.'function second(array $row) { return $row[1]; }', 0];
        yield 'unsupported union' => ['/** @param array{string|int, int} $row */ function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'optional field' => ['/** @param array{0: string, 1?: int} $row */ function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'open shape' => ['/** @param array{string, int, ...} $row */ function render(array $row) { return $row[0].$row[1]; }', 0];
        yield 'out of bounds' => [$doc.'function render(array $row) { return $row[0].$row[2]; }', 0];
        yield 'variable index' => [$doc.'function render(array $row, $index) { return $row[0].$row[$index]; }', 0];
        yield 'unset' => [$doc.'function render(array $row) { unset($row[0]); return $row[0].$row[1]; }', 0];
        yield 'increment' => [$doc.'function render(array $row) { $row[1]++; return $row[0].$row[1]; }', 0];
    }
}
