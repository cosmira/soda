<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class MaxDependenciesTest extends TestCase
{
    public function testReturnsViolationWhenClassDependencyCountExceedsLimit(): void
    {
        $violations = CheckFixture::run(CheckFixture::checks(['max_dependencies' => 2], []), ['/project/src/File.php' => ['classes' => [
            'App\Service' => $this->classMetrics(['dependencies' => 5]),
        ]]]);

        $this->assertCount(1, $violations);
        $this->assertSame('max_dependencies', $violations[0]->rule);
        $this->assertSame('/project/src/File.php', $violations[0]->file);
        $this->assertSame('App\Service', $violations[0]->class);
        $this->assertSame(['value' => 5, 'threshold' => 2], ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]);
    }

    public function testReturnsNoViolationsWhenRuleIsDisabled(): void
    {
        $violations = CheckFixture::run(CheckFixture::checks([], []), ['/project/src/File.php' => ['classes' => [
            'App\Service' => $this->classMetrics(['dependencies' => 5]),
        ]]]);

        $this->assertTrue($violations->isEmpty());
    }

    /**
     * @param array<string, int|string> $overrides
     *
     * @return array{loc: int, methods: int, properties: int, public_methods: int, dependencies: int, efferent_coupling: int, traits: int, interfaces: int, namespace: string, namespace_depth: int}
     */
    private function classMetrics(array $overrides = []): array
    {
        return array_merge([
            'loc'               => 10,
            'methods'           => 1,
            'properties'        => 1,
            'public_methods'    => 1,
            'dependencies'      => 1,
            'efferent_coupling' => 0,
            'traits'            => 0,
            'interfaces'        => 0,
            'namespace'         => 'App',
            'namespace_depth'   => 1,
        ], $overrides);
    }
}
