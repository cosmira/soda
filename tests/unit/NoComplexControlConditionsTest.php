<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\AstFacts;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\LogicalLineMap;
use Cosmira\Soda\Rules\Complexity\MaxBooleanConditions;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoComplexControlConditionsTest extends TestCase
{
    #[DataProvider('conditions')]
    public function testReadableConditionsAndExplanations(string $condition, ?string $reason): void
    {
        foreach ([$condition, str_replace(['$user', ' && ', ' || '], ['$account', "\n && ", "\n || "], $condition)] as $expression) {
            $file = $this->facts('<?php if ('.$expression.') {}');
            $violations = [...(new NoComplexControlConditions)->checkFile($file)];
            self::assertCount($reason === null ? 0 : 1, $violations, $expression);
            if ($reason !== null) {
                self::assertSame($reason, $file->metrics['complexControlConditions'][0]['reason']);
                self::assertSame(1, $violations[0]->line);
                self::assertStringContainsString($reason === 'mixed_logic' ? 'Mixed logical' : 'Nested computation', $violations[0]->message);
            }
        }
    }

    public static function conditions(): iterable
    {
        foreach ([
            '($found = find())', '($found ??= find())', '($value = $source) === null', 'is_null($value = $source)',
            '(bool) predicate()', '(!$ready) === true', '$ready ?? false', 'str_contains((string) $name, "::")', '__LINE__ > 0',
            '$ready', '!$ready', '!$user->isActive()', '!isset($user)', 'empty($items)', '!($user instanceof User)',
            '$user->age() >= 18 && $user->isVerified()', 'count($items) > 0', 'count($left) === count($right)',
            '$a > $b && $a > $c', '$a && ($b && !$c)', '$a || ($b || !$c)', '$a and $b && $c',
            '!($a && $b)', 'in_array($status, ["ready", "sent"], true)', '$user?->isActive()',
            '$values["ready"] === true', 'Options::READY === $status',
            'array_all($items, fn ($item) => $item->a() && ($item->b() || $item->c()))',
            'check(function () { return nested(work()); })',
            'Auth::user() instanceof User', '($renderer = renderer()) instanceof Renderer',
            '! (output() instanceof BufferedOutput)', 'Env::get("TESTS") ?? false',
            '$ready ?? predicate()', 'primary() ?? fallback()',
        ] as $expression) {
            yield $expression => [$expression, null];
        }
        foreach (['$a && ($b || $c)', '$a xor $b', '!($a || ($b && $c))'] as $expression) {
            yield $expression => [$expression, 'mixed_logic'];
        }
        foreach ([
            'Order::query()->pending()->count() > $limit', 'check(resolve($user))', 'getUser()->isReady()',
            '($a > $b) === true', '$count > $limit * 1000', '($ready ? one() : two()) === 1',
            '(match ($value) { 1 => true, default => false })', 'check([resolve($user)])',
            '$values[index()] === true', '$user->{name()}()',
            'resolve(input()) instanceof User', 'Env::get(key()) ?? false',
            '(primary() ?? fallback())->ready()', 'check(Env::get("TESTS") ?? false)',
        ] as $expression) {
            yield $expression => [$expression, 'nested_computation'];
        }
    }

    public function testVisitsAllConditionsAndKeepsCallableScopesSeparate(): void
    {
        $file = $this->facts(<<<'PHP'
<?php
if ($a) {} elseif ($a && ($b || $c)) {}
while (query()->exists()) {}
do {} while ($a && ($b || $c));
for (; $ready, $a && ($b || $c); ) {}
$result = ($a && ($b || $c)) ? 'yes' : 'no';
if (check(function () { if (nested(work())) {} })) {}
PHP);
        self::assertSame([2, 3, 4, 5, 6, 7], array_column($file->metrics['complexControlConditions'], 'line'));
        self::assertCount(6, [...(new NoComplexControlConditions)->checkFile($file)]);
    }

    public function testLengthRemainsTheBooleanCountRulesResponsibility(): void
    {
        $file = $this->facts('<?php function run() { if ($a && $b && $c && $d && $e) {} }');
        self::assertSame([], [...(new NoComplexControlConditions)->checkFile($file)]);
        self::assertCount(1, [...(new MaxBooleanConditions(2))->checkFile($file)]);
    }

    private function facts(string $source): FileFacts
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $metrics = AstFacts::collect($nodes, LogicalLineMap::fromSource($source), ['conditions']);
        $all = AstFacts::collect((new ParserFactory)->createForNewestSupportedVersion()->parse($source), LogicalLineMap::fromSource($source), null);
        self::assertSame($all['complexControlConditions'], $metrics['complexControlConditions']);

        return new FileFacts('conditions.php', $source, $nodes, $metrics);
    }
}
