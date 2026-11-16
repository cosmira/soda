<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Usage\NoNullableCollections;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoNullableCollectionsTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('cases')]
    public function testProvenReturns(string $source, int $count): void
    {
        $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($this->parseSnippet($source));
        $rule = new NoNullableCollections;
        $hits = iterator_to_array($rule->checkFile(new FileFacts('fixture.php', '<?php '.$source, $nodes, [])));
        self::assertCount($count, $hits);
        self::assertSame([], $rule->requiredAnalyses());
        foreach ($hits as $hit) {
            self::assertSame('no_nullable_collections', $hit->rule);
            self::assertSame('fixture.php', $hit->file);
            self::assertSame(1, $hit->value);
            self::assertSame(0, $hit->threshold);
            self::assertGreaterThan(0, $hit->line);
            self::assertStringContainsString('null', $hit->message);
        }
    }

    public static function cases(): iterable
    {
        yield 'empty array' => ['function run(): ?array { return []; }', 1];
        yield 'array union' => ['function run(): array|null { return [1]; }', 1];
        yield 'reverse union' => ['function run(): null|array { return []; }', 1];
        yield 'iterable array' => ['function run(): ?iterable { return []; }', 1];
        yield 'cast' => ['function run($x): ?array { return (array) $x; }', 1];
        yield 'ternary' => ['function run($x): ?array { return $x ? [] : [1]; }', 1];
        yield 'match' => ['function run($x): ?array { return match ($x) { 1 => [], default => [1] }; }', 1];
        yield 'guard and return' => ['function run($x): ?array { if ($x) { return []; } return [1]; }', 1];
        yield 'complete branches' => ['function run($x): ?array { if ($x) { return []; } else { return [1]; } }', 1];
        yield 'throwing guard' => ['function run($x): ?array { if ($x) { throw new RuntimeException; } return []; }', 1];
        yield 'throwing alternative' => ['function run($x): ?array { if ($x) { return []; } else { throw new RuntimeException; } }', 1];
        yield 'arrow' => ['$run = fn (): ?array => [];', 1];
        yield 'closure' => ['$run = function (): ?array { return []; };', 1];
        yield 'nested nullable closure' => ['function run(): ?array { $other = function (): ?array { return null; }; return []; }', 1];
        yield 'final class' => ['final class Demo { public function run(): ?array { return []; } }', 1];
        yield 'final method' => ['class Demo { final public function run(): ?array { return []; } }', 1];
        yield 'private method' => ['class Demo { private function run(): ?array { return []; } }', 1];
        yield 'laravel collection alias' => ['use Illuminate\Support\Collection as Items; function run(): ?Items { return new Items; }', 1];
        yield 'eloquent collection' => ['function run(): ?\Illuminate\Database\Eloquent\Collection { return new \Illuminate\Database\Eloquent\Collection; }', 1];
        yield 'eloquent subtype' => ['function run(): ?\Illuminate\Support\Collection { return new \Illuminate\Database\Eloquent\Collection; }', 1];
        yield 'lazy collection' => ['function run(): ?\Illuminate\Support\LazyCollection { return new \Illuminate\Support\LazyCollection; }', 1];
        yield 'enumerable' => ['function run(): ?\Illuminate\Support\Enumerable { return new \Illuminate\Support\Collection; }', 1];
        yield 'iterable collection' => ['function run(): ?iterable { return new \Illuminate\Support\LazyCollection; }', 1];
        yield 'native iterator' => ['function run(): ?iterable { return new \ArrayIterator([]); }', 1];
        yield 'already nonnullable' => ['function run(): array { return []; }', 0];
        yield 'other union' => ['function run(): array|string|null { return []; }', 0];
        yield 'nullable scalar' => ['function run(): ?string { return ""; }', 0];
        yield 'real null' => ['function run(): ?array { return null; }', 0];
        yield 'nullable branch' => ['function run($x): ?array { if ($x) { return null; } return []; }', 0];
        yield 'fallthrough' => ['function run($x): ?array { if ($x) { return []; } }', 0];
        yield 'bare return' => ['function run($x): ?array { if ($x) { return; } return []; }', 0];
        yield 'unknown call' => ['function run(): ?array { return loadItems(); }', 0];
        yield 'unknown variable' => ['function run($x): ?array { return $x; }', 0];
        yield 'unknown ternary' => ['function run($x): ?array { return $x ? [] : loadItems(); }', 0];
        yield 'nullable match' => ['function run($x): ?array { return match ($x) { 1 => [], default => null }; }', 0];
        yield 'finally can override' => ['function run(): ?array { try { return []; } finally { return null; } }', 0];
        yield 'generator' => ['function run(): ?iterable { yield 1; return []; }', 0];
        yield 'unreachable yield' => ['function run(): ?iterable { return []; yield 1; }', 0];
        yield 'nested generator' => ['function run(): ?array { $nested = function (): ?iterable { yield 1; }; return []; }', 1];
        yield 'yield from' => ['function run(): ?iterable { yield from []; return []; }', 0];
        yield 'goto' => ['function run(): ?array { goto end; return []; end: return null; }', 0];
        yield 'unreachable null' => ['function run(): ?array { return []; return null; }', 1];
        yield 'unreachable array' => ['function run(): ?array { throw new RuntimeException; return []; }', 0];
        yield 'known interface cannot be constructed' => ['function run(): ?\\Illuminate\\Support\\Enumerable { return new \\Illuminate\\Support\\Enumerable; }', 0];
        yield 'always throws' => ['function run(): ?array { throw new RuntimeException; }', 0];
        yield 'abstract' => ['abstract class Demo { abstract public function run(): ?array; }', 0];
        yield 'overridable method' => ['class Demo { public function run(): ?array { return []; } }', 0];
        yield 'trait method' => ['trait Demo { public function run(): ?array { return []; } }', 0];
        yield 'by-reference return' => ['function &run(): ?array { return []; }', 0];
        yield 'unknown Collection class' => ['class Collection {} function run(): ?Collection { return new Collection; }', 0];
        yield 'collect helper unknown' => ['function run(): ?\Illuminate\Support\Collection { return collect([]); }', 0];
        yield 'collection factory unknown' => ['function run(): ?\Illuminate\Support\Collection { return \Illuminate\Support\Collection::make([]); }', 0];
        yield 'wrong collection kind' => ['function run(): ?\Illuminate\Support\LazyCollection { return new \Illuminate\Support\Collection; }', 0];
        yield 'anonymous iterable unknown' => ['function run(): ?iterable { return new class {}; }', 0];
    }

    public function testNamesTheDeclaringMethod(): void
    {
        $source = 'namespace Domain; final class Catalog { public function items(): ?array { return []; } }';
        $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($this->parseSnippet($source));
        $hits = iterator_to_array((new NoNullableCollections)->checkFile(new FileFacts('fixture.php', '<?php '.$source, $nodes, [])));
        self::assertCount(1, $hits);
        self::assertSame('Domain\\Catalog::items', $hits[0]->method);
    }

    public function testRuleIsStandard(): void
    {
        self::assertContains(NoNullableCollections::class, array_map(fn ($rule): string => $rule::class, RuleCatalog::standard()));
    }
}
