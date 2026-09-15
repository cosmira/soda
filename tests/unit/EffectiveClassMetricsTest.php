<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Composition\ComposedClassMetrics;
use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Rules\Structure\MaxEffectiveClassLength;
use Cosmira\Soda\Rules\Structure\MaxEffectiveMethodsPerClass;
use PHPUnit\Framework\TestCase;

final class EffectiveClassMetricsTest extends TestCase
{
    public function testMovingMethodsToTraitsDoesNotReduceEffectiveCount(): void
    {
        $project = $this->project([
            '<?php namespace App; trait Steps { function first() {} private function second() {} }',
            '<?php namespace App; class Workflow { use Steps; function third() {} }',
        ]);
        self::assertSame([], iterator_to_array((new MaxEffectiveMethodsPerClass(3))->checkProject($project)));
        $findings = iterator_to_array((new MaxEffectiveMethodsPerClass(2))->checkProject($project));
        self::assertCount(1, $findings);
        self::assertSame('App\\Workflow', $findings[0]->class);
        self::assertSame(3, $findings[0]->value);
        self::assertSame(2, $findings[0]->threshold);
        self::assertSame(1, $findings[0]->line);
    }

    public function testDiamondsAliasesAndOverrides(): void
    {
        $project = $this->project([
            '<?php trait Shared { function run() {} }',
            '<?php trait Left { use Shared; } trait Right { use Shared; }',
            '<?php class Workflow { use Left, Right { Left::run insteadof Right; Left::run as private alias; } function run() {} }',
        ]);
        $metrics = (new ComposedClassMetrics)->collect($project);
        self::assertCount(1, $metrics);
        self::assertSame(2, $metrics[0]['methods']);
        self::assertSame(4, $metrics[0]['loc']);
        $findings = iterator_to_array((new MaxEffectiveClassLength(3))->checkProject($project));
        self::assertCount(1, $findings);
        self::assertSame(4, $findings[0]->value);
        self::assertSame(3, $findings[0]->threshold);
        self::assertSame([], iterator_to_array((new MaxEffectiveClassLength(4))->checkProject($project)));
    }

    public function testAbstractRequirementsDoNotCountAsImplementedMethods(): void
    {
        $project = $this->project(['<?php trait Contract { abstract public function run(); } abstract class Workflow { use Contract; }']);
        $metrics = (new ComposedClassMetrics)->collect($project);
        self::assertSame(0, $metrics[0]['methods']);
    }

    public function testDisabledThresholdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MaxEffectiveClassLength(0);
    }

    private function project(array $sources): ProjectFacts
    {
        $project = new ProjectFacts;
        foreach ($sources as $source) {
            $path = tempnam(sys_get_temp_dir(), 'soda-effective-');
            file_put_contents($path, $source);

            try {
                $project->add((new FactCollector)->collect($path, ['structure']));
            } finally {
                unlink($path);
            }
        }

        return $project;
    }
}
