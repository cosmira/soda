<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoUnusedMethodsTest extends TestCase
{
    public function testDefaultIgnoreSkipsPhpUnitLifecycle(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function setUp(): void {} }\n");
        $rule = new NoUnusedMethods;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testExtraIgnoreMergedWithDefaults(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function seedDb(): void {} }\n");
        $rule = new NoUnusedMethods(ignore: ['seedDb']);

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testWithoutIgnoreReportsUnusedPrivate(): void
    {
        $file = $this->tempFile("<?php\nclass A { private function dead(): void {} }\n");
        $rule = new NoUnusedMethods;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertSame('unused_methods', $violations->first()->rule);
        unlink($file);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalMetrics(): array
    {
        return [
            'file_loc'       => 1,
            'classes_count'  => 0,
            'classes'        => [],
            'methods'        => [],
            'namespaces'     => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $qualityMetrics
     */
    private function context(array $qualityMetrics): array
    {
        $core = $qualityMetrics;
        $fileMetrics = $core;

        return [CheckFixture::checks(), $fileMetrics];
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_unused_methods_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
