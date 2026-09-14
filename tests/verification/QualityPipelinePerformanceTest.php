<?php

declare(strict_types=1);

namespace Cosmira\Soda\Verification;

use Cosmira\Soda\Analysis\Runner as QualityAnalyser;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Group('verification')]
#[Group('performance')]
final class QualityPipelinePerformanceTest extends TestCase
{
    #[RunInSeparateProcess]
    #[TestWith([false])]
    #[TestWith([true])]
    public function testLargeProjectStaysWithinTimeAndMemoryBudgets(bool $standard): void
    {
        $project = sys_get_temp_dir().'/soda-performance-'.uniqid();
        mkdir($project, 0700, true);
        $config = $project.'/soda.php';
        $rules = $standard ? '\\Cosmira\\Soda\\Config\\RuleCatalog::standard()' : '[]';
        file_put_contents($config, "<?php\nreturn \\Cosmira\\Soda\\Config\\Soda::configure()->with(".$rules.");\n");

        $files = $this->createProjectFiles($project, 300);
        $memoryBefore = memory_get_usage(true);
        $startedAt = hrtime(true);

        try {
            $result = (new QualityAnalyser)->analyse($files, $config);
            $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
            $peakGrowth = memory_get_peak_usage(true) - $memoryBefore;

            $this->assertTrue($result->isPassing());
            $this->assertLessThan(8.0, $elapsedSeconds, '300 files must finish within the performance budget.');
            $this->assertLessThan(
                64 * 1024 * 1024,
                $peakGrowth,
                'The pipeline must not retain project-wide source text or AST nodes.',
            );
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
            unlink($config);
            rmdir($project);
        }
    }

    #[RunInSeparateProcess]
    public function testCrossFileConcernsStayWithinTimeAndMemoryBudgets(): void
    {
        $directory = sys_get_temp_dir().'/soda-concern-performance-'.uniqid();
        mkdir($directory, 0700);
        $files = [];
        for ($index = 0; $index < 300; $index++) {
            $trait = $directory.'/Concern'.$index.'.php';
            $owner = $directory.'/Owner'.$index.'.php';
            file_put_contents($trait, "<?php trait Concern$index { private function save() {} }");
            file_put_contents($owner, "<?php class Owner$index { use Concern$index; public function run() { \$this->save(); } }");
            array_push($files, $trait, $owner);
        }
        $before = memory_get_usage(true);
        $start = hrtime(true);

        try {
            $result = (new QualityAnalyser)->check($files, Soda::configure()->with([new NoUnusedMethods]));
            self::assertTrue($result->isPassing());
            self::assertLessThan(8.0, (hrtime(true) - $start) / 1_000_000_000);
            self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage(true) - $before);
        } finally {
            array_map(unlink(...), $files);
            rmdir($directory);
        }
    }

    /** @return list<non-empty-string> */
    private function createProjectFiles(string $project, int $count): array
    {
        $files = [];

        for ($index = 0; $index < $count; $index++) {
            $file = $project.'/Example'.$index.'.php';
            file_put_contents($file, sprintf(
                "<?php\nfinal class Example%d { public function value(int \$input): int { return \$input + %d; } }\n",
                $index,
                $index,
            ));
            $files[] = $file;
        }

        return $files;
    }
}
