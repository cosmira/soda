<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Structure\MaxLineLength;
use PHPUnit\Framework\TestCase;

final class MaxLineLengthTest extends TestCase
{
    public function testLineAtLimitPasses(): void
    {
        $file = $this->writeFixture(str_repeat('a', 100));

        try {
            $violations = CheckFixture::forRule(new MaxLineLength(100), $this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    public function testLineOverLimitReportsLineNumber(): void
    {
        $file = $this->writeFixture("short\n".str_repeat('b', 101));

        try {
            $violations = CheckFixture::forRule(new MaxLineLength(100), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('max_line_length', $violations->first()->rule);
            $this->assertSame($file, $violations->first()->file);
            $this->assertSame(2, $violations->first()->line);
            $this->assertSame(['value' => 101, 'threshold' => 100], ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-line-length-');
        $this->assertIsString($file);
        file_put_contents($file, $contents);

        return $file;
    }

    private function context(string $file): array
    {
        return [CheckFixture::checks(), [
            $file => [
                'file_loc'      => 1,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ]];
    }
}
