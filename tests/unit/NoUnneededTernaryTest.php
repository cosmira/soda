<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Complexity\NoUnneededTernary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoUnneededTernaryTest extends TestCase
{
    #[DataProvider('expressions')]
    public function testLiteralBoundaries(string $expression, int $count): void
    {
        self::assertCount($count, FiveRuleFixture::findings(new NoUnneededTernary, '<?php $result = '.$expression.';'));
    }

    public static function expressions(): array
    {
        return [
            ['isReady() ? true : false', 1],
            ['$unknown ? false : true', 1],
            ['$unknown ? TRUE : FALSE', 1],
            ['($a === $b) ? (true) : (false)', 1],
            ['$unknown ? true : true', 0],
            ['$unknown ? false : false', 0],
            ['$unknown ? 1 : 0', 0],
            ['$unknown ? "true" : "false"', 0],
            ['$unknown ? null : false', 0],
            ['$unknown ? false : null', 0],
            ['$unknown ? true : MAYBE', 0],
            ['$unknown ? MAYBE : false', 0],
            ['$unknown ? true : Other\false', 0],
            ['$unknown ?: false', 0],
            ['$unknown ?? false', 0],
            ['$unknown ? $unknown : fallback()', 0],
            ['$a ? ($b ? true : false) : false', 1],
            ['fn () => $a ? true : false', 1],
            ['new class { function run() { return $a ? true : false; } }', 1],
            ['$unknown ? true : isset($value)', 1],
            ['$unknown ? false : isset($value)', 1],
            ['$unknown ? isset($value) : false', 1],
            ['$unknown ? isset($value) : true', 1],
            ['$unknown ? ($left === $right) : false', 1],
            ['$unknown ? true : ($value instanceof Task)', 1],
            ['$unknown ? (bool) $value : false', 1],
            ['$unknown ? empty($value) : true', 1],
            ['$unknown ? ! $value : false', 1],
            ['$unknown ? ($left && $right) : false', 1],
            ['$unknown ? ($left <=> $right) : false', 0],
            ['$unknown ? true : isReady()', 0],
            ['$unknown ? $value : false', 0],
            ['$unknown ? true : $value', 0],
            ['$unknown ? null : isset($value)', 0],
            ['$unknown ? isset($value) : Other\\false', 0],
            ['$unknown ? ($value ? true : false) : false', 1],
        ];
    }

    public function testDiagnosticPreservesUnknownConditionType(): void
    {
        $finding = FiveRuleFixture::findings(new NoUnneededTernary, "<?php\n\$result = \$unknown ? true : false;")[0];
        self::assertSame('no_unneeded_ternary', $finding->rule);
        self::assertSame(2, $finding->line);
        self::assertSame(1, $finding->value);
        self::assertSame(0, $finding->threshold);
        self::assertStringStartsWith('Unneeded boolean ternary. Use (bool) (condition);', $finding->message);
        self::assertStringContainsString('once', $finding->message);
        $negative = FiveRuleFixture::findings(new NoUnneededTernary, '<?php $result = $unknown ? false : true;')[0];
        self::assertStringContainsString('! (condition)', $negative->message);
    }

    #[DataProvider('values')]
    public function testSuggestedConversionsPreservePhpRuntimeSemantics(mixed $value): void
    {
        $calls = 0;
        $condition = static function () use ($value, &$calls) {
            $calls++;

            return $value;
        };
        $positive = $condition() ? true : false;
        self::assertSame(1, $calls);
        $calls = 0;
        self::assertSame($positive, (bool) $condition());
        self::assertSame(1, $calls);
        $calls = 0;
        $negative = $condition() ? false : true;
        self::assertSame(1, $calls);
        $calls = 0;
        self::assertSame($negative, ! $condition());
        self::assertSame(1, $calls);
    }

    public static function values(): array
    {
        return [[null], [false], [true], [0], [1], [-1], [0.0], [NAN], [INF], [''], ['0'], ['false'], ['00'], [[]], [[0]], [new \stdClass]];
    }

    #[DataProvider('partialForms')]
    public function testPartialBooleanAdvice(string $expression, string $replacement): void
    {
        $findings = FiveRuleFixture::findings(new NoUnneededTernary, "<?php\n\$result = ".$expression.';');
        self::assertCount(1, $findings);
        self::assertSame(2, $findings[0]->line);
        self::assertSame(1, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
        self::assertStringContainsString($replacement, $findings[0]->message);
        self::assertStringContainsString('once', $findings[0]->message);
    }

    public static function partialForms(): array
    {
        return [
            ['$condition ? true : isset($value)', '(condition) || (else expression)'],
            ['$condition ? false : isset($value)', '! (condition) && (else expression)'],
            ['$condition ? isset($value) : false', '(condition) && (then expression)'],
            ['$condition ? isset($value) : true', '! (condition) || (then expression)'],
        ];
    }

    #[DataProvider('values')]
    public function testPartialFormsPreserveResultAndBranchEffects(mixed $value): void
    {
        foreach ([false, true] as $branch) {
            foreach ($this->partialFormCallbacks() as [$original, $replacement]) {
                $events = [];
                $condition = static function () use ($value, &$events) {
                    $events[] = 'condition';

                    return $value;
                };
                $predicate = static function () use ($branch, &$events): bool {
                    $events[] = 'branch';

                    return $branch;
                };
                $expected = $original($condition, $predicate);
                $expectedEvents = $events;
                $events = [];
                self::assertSame($expected, $replacement($condition, $predicate));
                self::assertSame($expectedEvents, $events);
            }
        }
    }

    private function partialFormCallbacks(): array
    {
        return [
            [static fn ($c, $p) => $c() ? true : $p(), static fn ($c, $p) => $c() || $p()],
            [static fn ($c, $p) => $c() ? false : $p(), static fn ($c, $p) => ! $c() && $p()],
            [static fn ($c, $p) => $c() ? $p() : false, static fn ($c, $p) => $c() && $p()],
            [static fn ($c, $p) => $c() ? $p() : true, static fn ($c, $p)  => ! $c() || $p()],
        ];
    }
}
