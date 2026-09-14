<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class AvoidRedundantNamingTest extends TestCase
{
    public function testUsesConfiguredSimilarityThresholdInViolationLimits(): void
    {
        $violations = CheckFixture::forRule(null, $this->context(['avoid_redundant_naming' => 90]));

        $this->assertCount(1, $violations);
        $this->assertSame('avoid_redundant_naming', $violations[0]->rule);
        $this->assertSame('/project/src/File.php', $violations[0]->file);
        $this->assertSame(10, $violations[0]->line);
        $this->assertSame('PostItemCollection', $violations[0]->class);
        $this->assertSame(['value' => 100, 'threshold' => 90], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
        $this->assertSame('Redundant naming: PostItemCollection → PostCollection (100%)', $violations[0]->message);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([]));

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int> $rules
     */
    private function context(array $rules): array
    {
        $core = [
            '/project/src/File.php' => [
                'file_loc'      => 10,
                'classes_count' => 1,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
                'naming'        => [
                    'classes' => [
                        ['class' => 'App\PostItemCollection', 'line' => 10],
                    ],
                    'methods' => [],
                ],
            ],
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }
}
