<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Structure\MaxClassLength;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetricRuleThresholdsTest extends TestCase
{
    #[DataProvider('noViolationProvider')]
    public function testReturnsEmptyWhenWithinLimit(
        int $value,
        int $limit,
    ): void {
        $result = CheckFixture::run([new MaxClassLength($limit)], ['/path/to/file.php' => ['classes' => ['App\Foo' => ['loc' => $value]]]])->all();

        $this->assertSame([], $result);
    }

    /**
     * @return \Iterator<string, array{value: int, limit: int}>
     */
    public static function noViolationProvider(): \Iterator
    {
        yield 'value equals limit' => ['value' => 100, 'limit' => 100];
        yield 'value below limit' => ['value' => 50, 'limit' => 100];
        yield 'limit disabled' => ['value' => 1000, 'limit' => 0];
    }

    public function testReturnsViolationWhenExceeded(): void
    {
        $result = CheckFixture::run([new MaxClassLength(100)], ['/path/to/file.php' => ['classes' => ['App\Foo' => ['loc' => 150]]]])->all();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Violation::class, $result[0]);
        $this->assertSame('max_class_length', $result[0]->rule);
        $this->assertSame('/path/to/file.php', $result[0]->file);
        $this->assertSame('App\Foo', $result[0]->class);
        $this->assertSame(['value' => 150, 'threshold' => 100], ['value' => $result[0]->value, 'threshold' => $result[0]->threshold]);
    }

    public function testSupportsMethodContext(): void
    {
        $result = CheckFixture::run([new MaxMethodLength(10)], ['/path/to/file.php' => ['methods' => ['App\Foo::bar' => ['loc' => 50]]]])->all();

        $this->assertCount(1, $result);
        $this->assertSame('App\Foo::bar', $result[0]->method);
        $this->assertSame('App\Foo', $result[0]->class);
    }

    public function testRuleInstancesKeepIndependentThresholds(): void
    {
        $file = new FileFacts('/file.php', '', [], ['file_loc' => 10, 'classes_count' => 0]);
        $low = new MaxFileLoc(5);
        $high = new MaxFileLoc(20);
        $this->assertCount(1, iterator_to_array($low->checkFile($file)));
        $this->assertCount(0, iterator_to_array($high->checkFile($file)));
        $this->assertCount(1, iterator_to_array($low->checkFile($file)));
    }
}
