<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxClassesPerFileTest extends TestCase
{
    public function testReturnsViolationWhenClassCountPerFileExceedsLimit(): void
    {
        $violations = CheckFixture::forRule(null, $this->context(['max_classes_per_file' => 1], 3));

        $this->assertCount(1, $violations);
        $this->assertSame('max_classes_per_file', $violations[0]->rule);
        $this->assertSame('/project/src/File.php', $violations[0]->file);
        $this->assertSame(['value' => 3, 'threshold' => 1], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([], 3));

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int> $rules
     */
    private function context(array $rules, int $classesCount): array
    {
        $core = [
            '/project/src/File.php' => [
                'file_loc'      => 10,
                'classes_count' => $classesCount,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }
}
