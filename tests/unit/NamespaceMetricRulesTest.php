<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NamespaceMetricRulesTest extends TestCase
{
    public function testReturnsViolationsForNamespaceDepthAndClassesPerNamespace(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([
            'max_namespace_depth'        => 2,
            'max_classes_per_namespace'  => 3,
        ]));

        $this->assertCount(2, $violations);

        $this->assertSame('max_namespace_depth', $violations[0]->rule);
        $this->assertSame('/project/src/Service.php', $violations[0]->file);
        $this->assertSame('App\Domain\Services\UserService', $violations[0]->class);
        $this->assertSame(['value' => 4, 'threshold' => 2], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);

        $this->assertSame('max_classes_per_namespace', $violations[1]->rule);
        $this->assertSame('/project/src/Service.php', $violations[1]->file);
        $this->assertSame('App\Domain\Services', $violations[1]->class);
        $this->assertSame(['value' => 5, 'threshold' => 3], ['value' => $violations[1]->value, 'threshold' => $violations[1]->threshold]);
    }

    public function testReturnsNoViolationsWhenRulesAreDisabled(): void
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
            '/project/src/Service.php' => [
                'file_loc'      => 10,
                'classes_count' => 1,
                'classes'       => [
                    'App\Domain\Services\UserService' => [
                        'loc'               => 10,
                        'methods'           => 1,
                        'properties'        => 0,
                        'public_methods'    => 1,
                        'dependencies'      => 0,
                        'efferent_coupling' => 0,
                        'traits'            => 0,
                        'interfaces'        => 0,
                        'namespace'         => 'App\Domain\Services',
                        'namespace_depth'   => 4,
                    ],
                ],
                'methods'    => [],
                'namespaces' => ['App\Domain\Services' => 5],
            ],
        ];

        $fileMetrics = $core;

        return [CheckFixture::checks($rules, []), $fileMetrics];
    }
}
