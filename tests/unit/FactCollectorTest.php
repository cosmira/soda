<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ParserException;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class FactCollectorTest extends TestCase
{
    public function testParseReturnsSourceNodesAndLogicalLines(): void
    {
        $file = $this->tempFile("<?php\nfinal class ParsedClass {}\n");

        try {
            $parsed = (new FactCollector())->collect($file);

            $this->assertSame("<?php\nfinal class ParsedClass {}\n", $parsed->source);
            $this->assertNotSame([], $parsed->nodes);
            $this->assertSame(1, $parsed->metrics['file_loc']);
        } finally {
            unlink($file);
        }
    }

    public function testParseCountsSingleLineSourceWithoutNewline(): void
    {
        $file = $this->tempFile('<?php echo 1;');

        try {
            $this->assertSame(1, (new FactCollector())->collect($file)->metrics['file_loc']);
        } finally {
            unlink($file);
        }
    }

    public function testLogicalLinesExcludeBlankCommentAndPhpOpenTagOnlyLines(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php

/**
 * Documentation does not make the file longer.
 */
final class Example
{
    // A standalone comment is not code.
    public function run(): void // An inline comment keeps the code line.
    {
        /* Neither is this block. */
        doWork();
    }
}
PHP);

        try {
            $parsed = (new FactCollector())->collect($file);

            $this->assertSame(7, $parsed->metrics['file_loc']);
        } finally {
            unlink($file);
        }
    }

    public function testParseThrowsForInvalidPhpFile(): void
    {
        $file = $this->tempFile("<?php\nif ( {\n");

        try {
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('Cannot parse '.$file.':');

            (new FactCollector())->collect($file);
        } finally {
            unlink($file);
        }
    }

    public function testParseThrowsForUnreadableFile(): void
    {
        $file = sys_get_temp_dir().'/soda-missing-'.uniqid().'.php';

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Cannot read '.$file);

        (new FactCollector())->collect($file);
    }

    public function testMultipleRulesAndDuplicatePathsShareOneParse(): void
    {
        $source = "<?php\nfunction run(\$one, \$two) {}\nrun(1, 2);";
        $file = $this->tempFile($source);
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())->method('parse')->with($source)
            ->willReturn((new ParserFactory)->createForNewestSupportedVersion()->parse($source));

        try {
            $result = (new Runner(new FactCollector($parser)))->check([$file, $file], Soda::configure()->with([
                new MaxArguments(1), new MaxFileLoc(1),
            ]));
            $this->assertCount(2, $result->violations);
        } finally {
            unlink($file);
        }
    }

    public function testEmptyFileProducesFactsWithoutAnError(): void
    {
        $file = $this->tempFile('');

        try {
            $facts = (new FactCollector)->collect($file);
            $this->assertSame([], $facts->nodes);
            $this->assertSame(0, $facts->metrics['file_loc']);
        } finally {
            unlink($file);
        }
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_throwing_parser_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
