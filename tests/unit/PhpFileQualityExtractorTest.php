<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\ParserException;
use Cosmira\Soda\Rules\Check as SodaRule;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class PhpFileQualityExtractorTest extends TestCase
{
    public function testExtractReturnsExpectedShape(): void
    {
        $path = __DIR__.'/../quality-fixture/ExampleEnum.php';
        $out = CheckFixture::collect($path);

        $this->assertArrayHasKey('metrics', $out);
        $this->assertArrayHasKey('violations', $out);
        $this->assertArrayHasKey('file_loc', $out['metrics']);
        $this->assertArrayNotHasKey('breathing', $out['metrics']);
        $this->assertArrayHasKey('naming', $out['metrics']);
        $this->assertArrayHasKey('todoFixme', $out['metrics']);
        $this->assertArrayHasKey('commentedCode', $out['metrics']);
        $this->assertArrayHasKey('emptyCatches', $out['metrics']);
        $this->assertArrayHasKey('askThenTell', $out['metrics']);
    }

    public function testConfiguredRulesReuseTheSharedSourceAndAst(): void
    {
        QualityExtractorCountingStream::$opens = 0;
        stream_wrapper_register('soda-count', QualityExtractorCountingStream::class);

        $rule = new class extends SodaRule
        {
            public function id(): string
            {
                return 'shared_parse_probe';
            }

            public function checkFile(FileFacts $file): iterable
            {
                if ($file->source === '' || $file->nodes === []) {
                    throw new \LogicException('Missing shared input');
                }

                return [];
            }
        };

        try {
            CheckFixture::collect('soda-count://Example.php', [$rule]);

            $this->assertSame(1, QualityExtractorCountingStream::$opens);
        } finally {
            stream_wrapper_unregister('soda-count');
        }
    }

    public function testFileClassAndMethodLocUseLogicalLinesWithoutComments(): void
    {
        $path = $this->tempFile(<<<'PHP'
<?php
/** File documentation. */
final class Example
{
    /** Method documentation. */
    public function run(): void
    {
        // Explanation.
        doWork();
    }
}
PHP);

        try {
            $metrics = CheckFixture::collect($path)['metrics'];

            $this->assertSame(7, $metrics['file_loc']);
            $this->assertSame(7, $metrics['classes']['Example']['loc']);
            $this->assertSame(4, $metrics['methods']['Example::run']['loc']);
        } finally {
            unlink($path);
        }
    }

    public function testExtractThrowsParserExceptionForInvalidPhp(): void
    {
        $path = $this->tempFile("<?php\nif ( {\n");

        try {
            $this->expectException(ParserException::class);
            $this->expectExceptionMessage('Cannot parse '.$path.':');

            CheckFixture::collect($path);
        } finally {
            unlink($path);
        }
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_quality_extractor_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}

final class QualityExtractorCountingStream
{
    public static int $opens = 0;

    public mixed $context;

    private int $position = 0;

    private string $source = "<?php\nfinal class Example { public function value(): int { return 1; } }\n";

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$opens++;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->source, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->source);
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return ['size' => strlen($this->source)];
    }
}
