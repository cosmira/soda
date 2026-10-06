<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Documentation\NoConsecutiveBlockComments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoConsecutiveBlockCommentsTest extends TestCase
{
    public function testReportsDescriptionSeparatedFromPropertyType(): void
    {
        $source = <<<'PHP'
<?php
class Audit {
    /**
     * Override the protected methods below to customize entity names,
     * operation names, and ignored attributes for a model.
     */

    /**
     * @var array<string, array{from: mixed, to: mixed}>
     */
    private array $pendingAuditChanges = [];
}
PHP;
        $violations = $this->findings($source);
        self::assertCount(1, $violations);
        self::assertSame('no_consecutive_block_comments', $violations[0]->rule);
        self::assertSame('/example.php', $violations[0]->file);
        self::assertSame(8, $violations[0]->line);
        self::assertSame(1, $violations[0]->value);
        self::assertSame(0, $violations[0]->threshold);
        self::assertStringContainsString('Merge', $violations[0]->message);
    }

    #[DataProvider('commentSequences')]
    public function testCommentTokenBoundaries(string $source, array $lines): void
    {
        self::assertSame($lines, array_column($this->findings($source), 'line'));
    }

    public static function commentSequences(): array
    {
        return [
            'ordinary blocks'              => ["<?php\n/* description */\n/* details */", [3]],
            'block before phpdoc'          => ["<?php\n/* description */\n/** @var int */", [3]],
            'phpdoc before block'          => ["<?php\n/** description */\n/* details */", [3]],
            'no whitespace'                => ['<?php /* one *//* two */', [1]],
            'three blocks'                 => ["<?php\n/** one */\n\n/** two */\n/** three */", [4, 5]],
            'windows newlines'             => ["<?php\r\n/** one */\r\n\r\n/** two */", [4]],
            'merged phpdoc'                => ["<?php\n/**\n * Description.\n *\n * @var array<string, int>\n */\n\$changes = [];", []],
            'code separates comments'      => ['<?php /* one */ $value = 1; /* two */', []],
            'attribute separates comments' => ['<?php /** one */ #[Attribute] /** two */ class Example {}', []],
            'line comment intervenes'      => ["<?php /* one */\n// details\n/* two */", []],
            'consecutive line comments'    => ["<?php // one\n// two\n# three", []],
            'string content'               => ['<?php $text = "/* one */ /* two */";', []],
            'heredoc content'              => ["<?php\n\$text = <<<'TEXT'\n/** one */\n/** two */\nTEXT;", []],
            'inline html'                  => ["/** one */\n/** two */", []],
            'php tags separate comments'   => ['<?php /* one */ ?> <?php /* two */', []],
        ];
    }

    public function testStandardCatalogRunsTheRuleFromSource(): void
    {
        $entry = RuleCatalog::standardDefinitions()['no_consecutive_block_comments'];
        self::assertSame(NoConsecutiveBlockComments::class, $entry['class']);
        $path = tempnam(sys_get_temp_dir(), 'soda-block-comments-');
        self::assertNotFalse($path);
        file_put_contents($path, '<?php /** Description */ /** @var int $value */ $value = 1;');

        try {
            $rule = new ($entry['class'])(...$entry['arguments']);
            $result = (new Runner)->check([$path], Soda::configure()->with([$rule]));
            self::assertCount(1, $result->violations);
        } finally {
            unlink($path);
        }
    }

    private function findings(string $source): array
    {
        return iterator_to_array((new NoConsecutiveBlockComments)->checkFile(new FileFacts('/example.php', $source, [], [])));
    }
}
