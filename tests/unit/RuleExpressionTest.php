<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Config\RuleExpression;
use Phplrt\Compiler\Compiler;
use Phplrt\Source\FileSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleExpressionTest extends TestCase
{
    #[DataProvider('expressions')]
    public function testExpressions(string $source, array $row, bool $expected): void
    {
        $this->assertSame($expected, (new RuleExpression($source))->isMatch($row));
    }

    public static function expressions(): iterable
    {
        yield ['method where complexity > 10 and nesting > 2', ['complexity' => 11, 'nesting' => 3], true];
        yield ['method where complexity > 10 and nesting > 2', ['complexity' => 10, 'nesting' => 3], false];
        yield ['method where args == 1 or args == 2 and loc > 10', ['args' => 1, 'loc' => 0], true];
        yield ['method where (args == 1 or args == 2) and loc > 10', ['args' => 1, 'loc' => 0], false];
        yield ['method where not not args >= 2', ['args' => 2], true];
        yield ['file where loc <= 2.5 and loc > -1', ['loc' => 2.5], true];
        yield ['method where args != 2 and args < 4', ['args' => 3], true];
        yield ['class where name matches "App\\\\Domain\\\\*"', ['name' => 'App\\Domain\\Order'], true];
        yield ['class where depends_on matches "Illuminate\\\\*"', ['depends_on' => ['Vendor\\Client', 'Illuminate\\Database\\Model']], true];
        yield ['class where not depends_on matches "Illuminate\\\\*"', ['depends_on' => []], true];
        yield ['file where path != "123"', ['path' => '0123'], true];
        yield ['file where path == "a\\nb"', ['path' => "a\nb"], true];
        // Boolean evaluation short-circuits; the second fact is deliberately absent.
        yield ['method where args == 0 or loc > 5', ['args' => 0], true];
        yield ['method where args > 0 and loc > 5', ['args' => 0], false];
    }

    #[DataProvider('invalidExpressions')]
    public function testInvalidExpressionsFailBeforeAnalysis(string $source): void
    {
        $this->expectException(ConfigException::class);
        new RuleExpression($source);
    }

    public static function invalidExpressions(): iterable
    {
        foreach (['', 'project where loc > 1', 'method where unknown > 1', 'file where args > 2',
            'method where args > "2"', 'file where path > "src"', 'method where loc matches "*"',
            'class where depends_on == "Foo"', 'method where args > 1 trailing',
            'method where args > 1 §', 'method where args >', 'method where (args > 1',
            'method where args > 1); system("id")', 'file where path == "\\q"',
            'method where args > 1 andrew', 'method where args > 1 or',
            'file where cbs > 0', 'file where wcd > 0', 'file where lcf > 0',
            'file where irs > 0', 'file where vbi > 0', 'file where col > 0',
        ] as $source) {
            yield [$source];
        }
    }

    public function testSemanticErrorContainsLocation(): void
    {
        $this->expectExceptionMessage("line 2, byte column 3: Unknown method field 'unknown'");
        new RuleExpression("method where\n  unknown > 1");
    }

    public function testRequiredAnalysesAreDeduplicated(): void
    {
        $rule = new RuleExpression('method where nesting < 5 or nesting > 10');
        $this->assertSame(['nesting'], $rule->requiredAnalyses());
        $this->assertSame([], (new RuleExpression('class where methods > 5'))->requiredAnalyses());
    }

    public function testMissingFactsNeverSilentlyPass(): void
    {
        $this->expectException(\LogicException::class);
        (new RuleExpression('method where args > 2'))->isMatch([]);
    }

    public function testCommittedGrammarMatchesTheSource(): void
    {
        $root = __DIR__.'/../..';
        $compiler = (new Compiler)->load(
            FileSource::createFromPathname($root.'/resources/rules.pp3'),
        );
        $generated = $compiler->generate();
        $this->assertSame(file_get_contents($root.'/resources/rules.php'), (string) $generated);
    }

    public function testExcessiveExpressionIsRejected(): void
    {
        $this->expectException(ConfigException::class);
        new RuleExpression(str_repeat(' ', 8193));
    }
}
