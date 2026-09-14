<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Config\RuleExpression;
use Cosmira\Soda\Rules\Complexity\CognitiveComplexity;
use Cosmira\Soda\Rules\Complexity\MaxCognitiveComplexity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CognitiveComplexityTest extends TestCase
{
    #[DataProvider('cases')]
    public function testExplainsEachPointAndKeepsFormattingAndLocalNamesIrrelevant(string $source, array $expected): void
    {
        foreach ([$source, str_replace(['$value', '; '], ['$renamed', ";\n"], $source)] as $variant) {
            $path = tempnam(sys_get_temp_dir(), 'soda-cognitive-');
            file_put_contents($path, '<?php '.$variant);

            try {
                $file = (new FactCollector)->collect($path, ['cognitive']);
                $rows = array_values((new CognitiveComplexity)->collect($file->nodes));
                self::assertSame($expected, array_column($rows, 'cognitive_complexity'));
                foreach ($rows as $row) {
                    self::assertSame($row['cognitive_complexity'], array_sum(array_column($row['cognitive_contributions'], 'increment')));
                }
                $rule = new MaxCognitiveComplexity(0);
                $selective = iterator_to_array($rule->checkFile($file));
                $full = iterator_to_array($rule->checkFile((new FactCollector)->collect($path)));
                self::assertEquals($full, $selective);
                self::assertCount(count(array_filter($expected)), $full);
                self::assertSame(['cognitive'], $rule->requiredAnalyses());
                self::assertSame(['cognitive'], (new RuleExpression('method where cognitive_complexity > 0'))->requiredAnalyses());
            } finally {
                unlink($path);
            }
        }
    }

    public static function cases(): iterable
    {
        yield 'linear has no base point' => ['function run($value) { call($value); return $value; }', [0]];
        yield 'independent guards' => ['function run($value) { if ($value) return 1; if (!$value) return 2; return 3; }', [2]];
        yield 'nested conditions' => ['function run($value) { if ($value) { if (other()) return 1; } }', [3]];
        yield 'elseif is hybrid' => ['function run($value) { if ($value) work(); elseif (other()) work(); else work(); }', [3]];
        yield 'elseif body is nested only once' => ['function run($value) { if ($value) work(); elseif (other()) { if (third()) work(); } }', [4]];
        yield 'for foreach if' => ['function run($value) { for (;;) { foreach ($value as $item) { if ($item) work(); } } }', [6]];
        yield 'switch once and normal break free' => ['function run($value) { switch ($value) { case 1: work(); break; case 2: work(); break; default: work(); } }', [1]];
        yield 'match once and nested ternary' => ['function run($value) { return match ($value) { 1, 2 => other() ? 1 : 2, default => 3 }; }', [3]];
        yield 'loop condition evaluated at the outer depth' => ['function run($value) { while ($value ? one() : two()) { work(); } }', [2]];
        yield 'ternary' => ['function run($value) { return $value ? 1 : 2; }', [1]];
        yield 'shorthand coalescing nullsafe' => ['function run($value) { return ($value?->name ?? "x") ?: "y"; }', [0]];
        yield 'boolean one run' => ['function run($value) { return $value && other() && third(); }', [1]];
        yield 'boolean runs in source order' => ['function run($value) { return $value && other() || third() && last(); }', [3]];
        yield 'negation breaks a run' => ['function run($value) { return $value && !(other() && third()); }', [2]];
        yield 'catches not try or finally' => ['function run($value) { try { work(); } catch (One|Two $error) { if ($value) recover(); } finally { cleanup(); } }', [3]];
        yield 'multilevel jump' => ['function run($value) { while ($value) { foreach ($value as $item) { continue 2; } } }', [4]];
        yield 'goto but not return' => ['function run($value) { goto end; end: return; }', [1]];
        yield 'direct recursion once' => ['function run($value) { if ($value) return run($value - 1); return run(0); }', [2]];
        yield 'namespaced function recursion' => ['namespace App; function run($value) { return run($value); }', [1]];
        yield 'literal this recursion' => ['class Work { function run($value) { return $this->run($value); } }', [1]];
        yield 'self recursion' => ['class Work { static function run($value) { return self::run($value); } }', [1]];
        yield 'mutual recursion deliberately not resolved' => ['function first() { return second(); } function second() { return first(); }', [0, 0]];
        yield 'closure independent from owner' => ['function run($value) { return function () use ($value) { if ($value) work(); }; }', [0, 1]];
        yield 'arrow independent from owner' => ['function run($value) { return fn ($item) => $item ? 1 : 2; }', [0, 1]];
        yield 'anonymous class independent from owner' => ['function run($value) { return new class { function work($item) { if ($item) return 1; } }; }', [0, 1]];
        yield 'trait enum and nested function' => ['trait Work { function run($value) { if ($value) work(); } } enum State { case Open; function run($value) { if ($value) work(); } } function outer() { function inner($value) { if ($value) work(); } }', [1, 1, 0, 1]];
    }

    public function testNegativeThresholdFailsBeforeExpressionCompilation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MaxCognitiveComplexity(-1);
    }
}
