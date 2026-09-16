<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Documentation\MultilinePropertyPhpDoc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PromotedPropertyPhpDocTest extends TestCase
{
    use ParsesPhpSnippets;

    #[DataProvider('declarations')]
    public function testPromotedProperties(string $code, array $visibilities, array $names): void
    {
        $file = new FileFacts('fixture.php', '<?php '.$code, $this->parseSnippet($code), []);
        $hits = iterator_to_array((new MultilinePropertyPhpDoc($visibilities))->checkFile($file));
        self::assertCount(count($names), $hits);
        foreach ($hits as $index => $hit) {
            self::assertSame('multiline_property_phpdoc', $hit->rule);
            self::assertNull($hit->method);
            self::assertGreaterThan(0, $hit->line);
            self::assertStringContainsString('property::'.$names[$index].' ', $hit->message);
        }
    }

    public static function declarations(): iterable
    {
        $all = ['public', 'protected', 'private'];
        yield 'all visibility' => ['class Example { function __construct(public int $one, protected string $two, private readonly bool $three) {} }', $all, ['one', 'two', 'three']];
        yield 'default public' => ['class Example { function __construct(public int $one, protected string $two, private bool $three) {} }', ['public'], ['one']];
        yield 'ordinary parameter' => ['class Example { function __construct(int $one) {} }', $all, []];
        yield 'own block' => ["class Example { function __construct(\n/**\n * The retry limit.\n */\npublic int \$limit) {} }", $all, []];
        yield 'one line' => ['class Example { function __construct(/** Retry limit. */ public int $limit) {} }', $all, ['limit']];
        yield 'constructor tag' => ["class Example {\n/**\n * @param int \$limit The retry limit.\n */\nfunction __construct(public int \$limit) {} }", $all, []];
        yield 'generic type' => ["class Example {\n/**\n * @param array<string, int> \$limits\n */\nfunction __construct(public array \$limits) {} }", $all, []];
        yield 'wrong tag' => ["class Example {\n/**\n * @param int \$limits\n */\nfunction __construct(public int \$limit) {} }", $all, ['limit']];
        yield 'constructor summary alone' => ["class Example {\n/**\n * Configure retries.\n */\nfunction __construct(public int \$limit) {} }", $all, ['limit']];
        yield 'unrelated mention' => ["class Example {\n/**\n * Use \$limit for retries.\n */\nfunction __construct(public int \$limit) {} }", $all, ['limit']];
        yield 'only documented parameter' => ["class Example {\n/**\n * @param int \$limit\n */\nfunction __construct(public int \$limit, public int \$delay) {} }", $all, ['delay']];
        yield 'promoted trait' => ['trait Example { function __construct(public int $limit) {} }', $all, ['limit']];
        yield 'anonymous class' => ['$object = new class(1) { function __construct(public int $limit) {} };', $all, ['limit']];
        yield 'inherited not duplicated' => ['class Base { function __construct(public int $limit) {} } class Child extends Base {}', $all, ['limit']];
        yield 'readonly class' => ['readonly class Example { function __construct(public int $limit) {} }', $all, ['limit']];
        yield 'attributes on property' => ['class Example { function __construct(#[Example] public int $limit) {} }', $all, ['limit']];
        yield 'constructor one line tag' => ['class Example { /** @param int $limit */ function __construct(public int $limit) {} }', $all, ['limit']];
    }
}
