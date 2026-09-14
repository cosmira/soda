<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxClassesPerProjectTest extends TestCase
{
    public function testReturnsViolationWhenProjectClassCountExceedsLimit(): void
    {
        $violations = CheckFixture::forRule(null, $this->context(['max_classes_per_project' => 2], 5));

        $this->assertCount(1, $violations);
        $this->assertSame('max_classes_per_project', $violations[0]->rule);
        $this->assertSame('/project/src/First.php', $violations[0]->file);
        $this->assertSame(['value' => 5, 'threshold' => 2], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([], 5));

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int> $rules
     */
    private function context(array $rules, int $classesOrTraits): array
    {
        $core = [
            '/project/src/First.php'  => $this->fileMetricsRow($classesOrTraits),
            '/project/src/Second.php' => $this->fileMetricsRow(0),
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }

    /**
     * @return array{file_loc: int, classes_count: int, classes: array<string, mixed>, methods: array<string, mixed>, namespaces: array<string, mixed>}
     */
    private function fileMetricsRow(int $classes): array
    {
        return [
            'file_loc'      => 10,
            'classes_count' => $classes,
            'classes'       => [],
            'methods'       => [],
            'namespaces'    => [],
        ];
    }
}
