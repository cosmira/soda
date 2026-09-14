<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Usage\ListOnlyArrayStrictness;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class OnlyListArraysAllowedTest extends TestCase
{
    public function testPragmaticReportsNestedAccess(): void
    {
        $file = $this->tempFile("<?php\n\$x = \$row['a']['b'];\n");
        $rule = new OnlyListArraysAllowed;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertSame('only_list_arrays', $violations->first()->rule);
        $this->assertStringContainsString('Nested array indexing', (string) $violations->first()->message);
        unlink($file);
    }

    public function testStrictReportsSingleStringKey(): void
    {
        $file = $this->tempFile("<?php\n\$x = \$row['a'];\n");
        $rule = new OnlyListArraysAllowed(strictness: ListOnlyArrayStrictness::Strict);

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Only list arrays are allowed', (string) $violations->first()->message);
        unlink($file);
    }

    public function testIgnoresPathsMatchingFormRequestPattern(): void
    {
        $dir = sys_get_temp_dir().'/soda-list-only-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/UpdateUserRequest.php';
        file_put_contents($file, "<?php\n\$x = \$row['a'];\n");

        $rule = new OnlyListArraysAllowed;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
        rmdir($dir);
    }

    public function testIgnoresPathsUnderIgnorePathPrefixes(): void
    {
        $dir = sys_get_temp_dir().'/soda-list-prefix-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/Legacy.php';
        file_put_contents($file, "<?php\n\$x = \$row['a'];\n");

        $rule = new OnlyListArraysAllowed(ignorePathPrefixes: [$dir]);

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
        rmdir($dir);
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
        $path = tempnam(sys_get_temp_dir(), 'soda_list_only_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
