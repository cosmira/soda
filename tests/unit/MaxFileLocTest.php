<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxFileLocTest extends TestCase
{
    public function testReturnsViolationWhenFileLocExceedsConfiguredLimit(): void
    {
        $violations = CheckFixture::forRule(null, $this->context(['max_file_loc' => 50], 120));

        $this->assertCount(1, $violations);
        $this->assertSame('max_file_loc', $violations[0]->rule);
        $this->assertSame('/project/src/File.php', $violations[0]->file);
        $this->assertSame(['value' => 120, 'threshold' => 50], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([], 120));

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int> $rules
     */
    private function context(array $rules, int $fileLoc): array
    {
        $core = [
            '/project/src/File.php' => [
                'file_loc'      => $fileLoc,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }
}
