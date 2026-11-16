<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Usage\NoRedundantArrayKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoRedundantArrayKeysTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('arrays')]
    public function testArrayKeys(string $source, int $count): void
    {
        $file = new FileFacts('fixture.php', '<?php '.$source, $this->parseSnippet($source), []);
        $rule = new NoRedundantArrayKeys;
        $violations = iterator_to_array($rule->checkFile($file));

        self::assertSame([], $rule->requiredAnalyses());
        self::assertCount($count, $violations);
        foreach ($violations as $violation) {
            self::assertSame('no_redundant_array_keys', $violation->rule);
            self::assertSame('fixture.php', $violation->file);
            self::assertSame(1, $violation->value);
            self::assertSame(0, $violation->threshold);
            self::assertGreaterThan(0, $violation->line);
            self::assertStringContainsString('Remove', $violation->message);
        }
    }

    public static function arrays(): iterable
    {
        yield 'explicit list' => ["return [0 => 'public', 1 => 'protected', 2 => 'private'];", 1];
        yield 'long syntax' => ['return array(0 => 10, 1 => 20);', 1];
        yield 'single entry' => ['return [0 => value()];', 1];
        yield 'implicit first key' => ['return [first(), 1 => second()];', 1];
        yield 'implicit last key' => ['return [0 => first(), second()];', 1];
        yield 'references' => ['return [0 => &$first, 1 => &$second];', 1];
        yield 'nested array' => ["return ['fields' => [0 => 'name', 1 => 'email']];", 1];
        yield 'two arrays' => ['return [[0 => 10], [0 => 20]];', 2];
        yield 'empty' => ['return [];', 0];
        yield 'implicit list' => ["return ['public', 'protected', 'private'];", 0];
        yield 'starts at one' => ['return [1 => 10, 2 => 20];', 0];
        yield 'gap' => ['return [0 => 10, 2 => 20];', 0];
        yield 'reordered' => ['return [1 => 10, 0 => 20];', 0];
        yield 'duplicate' => ['return [0 => 10, 0 => 20];', 0];
        yield 'negative' => ['return [-1 => 10, 0 => 20];', 0];
        yield 'associative' => ["return [0 => 10, 'name' => 20];", 0];
        yield 'dynamic key' => ['return [0 => 10, $key => 20];', 0];
        yield 'computed key' => ['return [0 => 10, (1 + 0) => 20];', 0];
        yield 'numeric string' => ["return ['0' => 10, '1' => 20];", 0];
        yield 'float key' => ['return [0.0 => 10, 1.0 => 20];', 0];
        yield 'unpack before' => ['return [...$values, 1 => 20];', 0];
        yield 'unpack after' => ['return [0 => 10, ...$values];', 0];
        yield 'destructuring' => ['[0 => $first, 1 => $second] = $values;', 0];
        yield 'foreach destructuring' => ['foreach ($values as [0 => $first, 1 => $second]) {}', 0];
    }

    public function testStandardConfigurationIncludesTheRule(): void
    {
        self::assertContains(NoRedundantArrayKeys::class, array_map(fn ($rule): string => $rule::class, RuleCatalog::standard()));
    }
}
