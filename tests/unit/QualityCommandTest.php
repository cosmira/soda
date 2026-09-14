<?php

declare(strict_types=1);
/*
 * This file is part of Soda.
 *
 * (c) Cosmira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Application;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Reporting\QualityResult;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class QualityCommandTest extends TestCase
{
    /** Keep enum coverage when changing the complexity collector. */
    #[Group('enum-workaround')]
    public function testQualityRunsOnFixtureWithEnumWithoutCrashing(): void
    {
        $container = new Application();
        $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
        $artisan->setAutoExit(false);
        $artisan->add(new QualityCommand());

        $input = new ArrayInput([
            'command' => 'quality',
            'path'    => [__DIR__.'/../quality-fixture'],
        ]);
        $output = new BufferedOutput();

        $exitCode = $artisan->run($input, new OutputStyle($input, $output));

        $this->assertContains($exitCode, [0, 1], 'Не должно падать с AssertionError на Enum');
    }

    public function testReportJsonIncludesGateResultAndViolations(): void
    {
        $reportPath = sys_get_temp_dir().'/soda-quality-test-'.uniqid().'.json';

        $container = new Application();
        $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
        $artisan->setAutoExit(false);
        $artisan->add(new QualityCommand());

        $input = new ArrayInput([
            'command'       => 'quality',
            'path'          => [__DIR__.'/../quality-fixture'],
            '--report-json' => $reportPath,
        ]);
        $output = new BufferedOutput();

        $artisan->run($input, new OutputStyle($input, $output));

        $this->assertFileExists($reportPath);

        $json = file_get_contents($reportPath);
        unlink($reportPath);

        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('schema_version', $data);
        $this->assertSame(4, $data['schema_version']);
        $this->assertArrayHasKey('passed', $data);
        $this->assertTrue($data['passed']);
        $this->assertArrayNotHasKey('score', $data);
        $this->assertArrayNotHasKey('metrics', $data);
        $this->assertArrayHasKey('violations', $data);
        $this->assertArrayNotHasKey('directories', $data);
        $this->assertArrayNotHasKey('files', $data);
        $this->assertArrayNotHasKey('loc', $data);
        $this->assertArrayNotHasKey('complexity', $data);
        $this->assertArrayNotHasKey('errors', $data);
    }

    public function testReportJsonIncludesViolationLocationAndMessage(): void
    {
        $project = $this->assignmentInConditionProject('soda-quality-json-violation');
        $reportPath = $project['dir'].'/quality.json';

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'       => 'quality',
                'path'          => [$project['dir']],
                '--config'      => $project['soda'],
                '--report-json' => $reportPath,
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertSame(1, $exitCode);
            $json = file_get_contents($reportPath);
            $this->assertNotFalse($json);
            $data = json_decode($json, true);
            $this->assertIsArray($data);
            $this->assertFalse($data['passed']);
            $this->assertCount(1, $data['violations']);

            $violation = $data['violations'][0];
            $this->assertSame('no_assignment_in_condition', $violation['rule']);
            $this->assertSame(realpath($project['php']), $violation['file']);
            $this->assertNull($violation['method']);
            $this->assertNull($violation['class']);
            $this->assertSame(7, $violation['line']);
            $this->assertSame(1, $violation['value']);
            $this->assertSame(0, $violation['threshold']);
            $this->assertStringContainsString('Assignment inside condition', $violation['message']);
            $this->assertSame(
                'Assign first, then make the condition a pure question with no hidden state change.',
                $violation['recommendation'],
            );
        } finally {
            if (is_file($reportPath)) {
                unlink($reportPath);
            }

            $this->cleanupAssignmentInConditionProject($project);
        }
    }

    public function testTextReportIncludesAssignmentInConditionLineAndMessage(): void
    {
        $project = $this->assignmentInConditionProject('soda-quality-text-violation');

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'  => 'quality',
                'path'     => [$project['dir']],
                '--config' => $project['soda'],
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));
            $text = $output->fetch();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Example.php', $text);
            $this->assertStringContainsString('Line 7', $text);
            $this->assertStringContainsString('Assignment inside condition', $text);
        } finally {
            $this->cleanupAssignmentInConditionProject($project);
        }
    }

    public function testReportJsonIncludesNamespaceNameLengthViolation(): void
    {
        $project = $this->namespaceNameLengthProject('soda-quality-namespace-name');
        $reportPath = $project['dir'].'/quality.json';

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'       => 'quality',
                'path'          => [$project['dir']],
                '--config'      => $project['soda'],
                '--report-json' => $reportPath,
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertSame(1, $exitCode);
            $json = file_get_contents($reportPath);
            $this->assertNotFalse($json);
            $data = json_decode($json, true);
            $this->assertIsArray($data);
            $this->assertFalse($data['passed']);
            $this->assertCount(1, $data['violations']);

            $violation = $data['violations'][0];
            $this->assertSame('namespace_name_length', $violation['rule']);
            $this->assertSame(realpath($project['php']), $violation['file']);
            $this->assertSame(3, $violation['line']);
            $this->assertSame(1, $violation['value']);
            $this->assertSame(3, $violation['threshold']);
            $this->assertSame('Name "X" has length 1.', $violation['message']);
            $this->assertSame(
                'Use a short domain term for the capability and remove organizational words that add no distinction.',
                $violation['recommendation'],
            );
        } finally {
            if (is_file($reportPath)) {
                unlink($reportPath);
            }

            $this->cleanupMinimalConfiguredProject($project);
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testConfiguredPathsAndRulesComeFromOneConfigExecution(bool $explicit): void
    {
        $project = $this->minimalConfiguredProject('soda-config-once');
        $counter = $project['dir'].'/loads';
        $cwd = getcwd();
        $config = file_get_contents($project['soda']);
        file_put_contents($project['soda'], str_replace(
            'return Soda::configure()',
            "file_put_contents(__DIR__.'/loads', 'loaded\\n', FILE_APPEND);\nreturn Soda::configure()",
            $config,
        ));

        try {
            chdir($project['dir']);
            $container = new Application;
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand);
            $arguments = ['command' => 'quality'];
            if ($explicit) {
                $arguments['--config'] = $project['soda'];
            }
            $input = new ArrayInput($arguments);
            $output = new BufferedOutput;

            $this->assertSame(0, $artisan->run($input, new OutputStyle($input, $output)));
            $this->assertSame('loaded\\n', file_get_contents($counter));
        } finally {
            chdir($cwd);
            if (is_file($counter)) {
                unlink($counter);
            }
            $this->cleanupMinimalConfiguredProject($project);
        }
    }

    public function testUsesInjectedQualityAnalyser(): void
    {
        $result = new QualityResult(collect([]));

        $stub = new class($result) extends Runner
        {
            public function __construct(private QualityResult $out) {}

            #[\Override]
            public function check(array $files, SodaConfig $config): QualityResult
            {
                return $this->out;
            }
        };

        $container = new Application();
        $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
        $artisan->setAutoExit(false);
        $artisan->add(new QualityCommand($stub));

        $input = new ArrayInput([
            'command' => 'quality',
            'path'    => [__DIR__.'/../quality-fixture'],
        ]);
        $output = new BufferedOutput();

        $exitCode = $artisan->run($input, new OutputStyle($input, $output));

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[OK]', $output->fetch());
    }

    public function testExcludesVendorAndExpandsRelativeExclusions(): void
    {
        $dir = sys_get_temp_dir().'/soda-quality-exclusions-'.uniqid();
        mkdir($dir.'/vendor/package', 0700, true);
        mkdir($dir.'/generated', 0700, true);
        file_put_contents($dir.'/Project.php', "<?php\n");
        file_put_contents($dir.'/vendor/package/Dependency.php', "<?php\n");
        file_put_contents($dir.'/generated/Generated.php', "<?php\n");

        $result = new QualityResult(collect([]));
        $stub = new class($result) extends Runner
        {
            /** @var list<non-empty-string> */
            public array $files = [];

            public function __construct(private readonly QualityResult $out) {}

            #[\Override]
            public function check(array $files, SodaConfig $config): QualityResult
            {
                $this->files = $files;

                return $this->out;
            }
        };

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand($stub));
            $input = new ArrayInput([
                'command'   => 'quality',
                'path'      => [$dir],
                '--exclude' => ['generated'],
            ]);

            $artisan->run($input, new OutputStyle($input, new BufferedOutput()));

            $this->assertSame([realpath($dir.'/Project.php')], $stub->files);
        } finally {
            unlink($dir.'/Project.php');
            unlink($dir.'/vendor/package/Dependency.php');
            unlink($dir.'/generated/Generated.php');
            rmdir($dir.'/vendor/package');
            rmdir($dir.'/vendor');
            rmdir($dir.'/generated');
            rmdir($dir);
        }
    }

    public function testQualityRunsOnMinimalTempProject(): void
    {
        $dir = sys_get_temp_dir().'/soda-quality-e2e-'.uniqid();
        mkdir($dir, 0700, true);
        $php = $dir.'/T.php';
        file_put_contents($php, "<?php\n\nfinal class T {}\n");
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([]);
PHP);

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'  => 'quality',
                'path'     => [$dir],
                '--config' => $soda,
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Soda Quality', $output->fetch());
        } finally {
            unlink($php);
            unlink($soda);
            rmdir($dir);
        }
    }

    public function testQualityReportsMultipleImplicitConfigsWithoutThrowing(): void
    {
        $left = $this->minimalConfiguredProject('soda-quality-left');
        $right = $this->minimalConfiguredProject('soda-quality-right');

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command' => 'quality',
                'path'    => [$left['dir'], $right['dir']],
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));
            $text = $output->fetch();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Multiple soda.php configs found', $text);
            $this->assertStringContainsString('--config', $text);
        } finally {
            $this->cleanupMinimalConfiguredProject($left);
            $this->cleanupMinimalConfiguredProject($right);
        }
    }

    public function testQualityDoesNotCrashOnRegularAssignments(): void
    {
        $dir = sys_get_temp_dir().'/soda-quality-assignments-'.uniqid();
        mkdir($dir, 0700, true);
        $php = $dir.'/Example.php';
        file_put_contents($php, <<<'PHP'
<?php

final class Example
{
    public function render(object $user, string $line): void
    {
        $line = trim($line);

        if ($user->isActive()) {
            $user->notify();
        }
    }
}
PHP);
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([]);
PHP);

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'  => 'quality',
                'path'     => [$dir],
                '--config' => $soda,
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));

            $this->assertContains($exitCode, [0, 1]);
            $this->assertStringContainsString('Soda Quality', $output->fetch());
        } finally {
            unlink($php);
            unlink($soda);
            rmdir($dir);
        }
    }

    public function testQualityReportsLayerMixingForDominantDirectory(): void
    {
        $dir = sys_get_temp_dir().'/soda-quality-layer-mixing-'.uniqid();
        mkdir($dir.'/app/Feature', 0700, true);
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxLayerDominancePercentage;

return Soda::configure()
    ->withPaths(['app/Feature'])
    ->with([new MaxLayerDominancePercentage(50, 4)]);
PHP);

        foreach (range(1, 5) as $index) {
            file_put_contents($dir.'/app/Feature/User'.$index.'.php', "<?php\n\nnamespace App\\Services;\n\nclass User{$index}Service extends UserService {}\n");
        }

        foreach (range(1, 2) as $index) {
            file_put_contents($dir.'/app/Feature/Controller'.$index.'.php', "<?php\n\nnamespace App\\Services;\n\nclass Page{$index}Controller extends Controller {}\n");
        }

        file_put_contents($dir.'/app/Feature/Plain.php', "<?php\n\nnamespace App\\Services;\n\nclass Plain {}\n");

        try {
            $container = new Application();
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand());

            $input = new ArrayInput([
                'command'  => 'quality',
                'path'     => [$dir.'/app'],
                '--config' => $soda,
            ]);
            $output = new BufferedOutput();

            $exitCode = $artisan->run($input, new OutputStyle($input, $output));
            $text = $output->fetch();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Layer mixing:', $text);
            $this->assertStringContainsString('Service dominates 62.5%', $text);
        } finally {
            foreach (glob($dir.'/app/Feature/*.php') ?: [] as $file) {
                unlink($file);
            }

            unlink($soda);
            rmdir($dir.'/app/Feature');
            rmdir($dir.'/app');
            rmdir($dir);
        }
    }

    /**
     * @return array{dir: string, php: string, soda: string}
     */
    private function minimalConfiguredProject(string $prefix): array
    {
        $dir = sys_get_temp_dir().'/'.$prefix.'-'.uniqid();
        mkdir($dir, 0700, true);
        $php = $dir.'/Example.php';
        file_put_contents($php, "<?php\n\nfinal class Example {}\n");
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([]);
PHP);

        return [
            'dir'  => $dir,
            'php'  => $php,
            'soda' => $soda,
        ];
    }

    /**
     * @param array{dir: string, php: string, soda: string} $project
     */
    private function cleanupMinimalConfiguredProject(array $project): void
    {
        foreach (['php', 'soda'] as $key) {
            if (is_file($project[$key])) {
                unlink($project[$key]);
            }
        }

        if (is_dir($project['dir'])) {
            rmdir($project['dir']);
        }
    }

    /**
     * @return array{dir: string, php: string, soda: string}
     */
    private function namespaceNameLengthProject(string $prefix): array
    {
        $dir = sys_get_temp_dir().'/'.$prefix.'-'.uniqid();
        mkdir($dir, 0700, true);
        $php = $dir.'/Example.php';
        file_put_contents($php, <<<'PHP'
<?php

namespace App\X\Domain;

final class Example {}
PHP);
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([new NamespaceNameLength(min: 3, max: 32)]);
PHP);

        return [
            'dir'  => $dir,
            'php'  => $php,
            'soda' => $soda,
        ];
    }

    /**
     * @return array{dir: string, php: string, soda: string}
     */
    private function assignmentInConditionProject(string $prefix): array
    {
        $dir = sys_get_temp_dir().'/'.$prefix.'-'.uniqid();
        mkdir($dir, 0700, true);

        $php = $dir.'/Example.php';
        file_put_contents($php, <<<'PHP'
<?php

final class Example
{
    public function run(): void
    {
        if ($value = nextValue()) {
            echo $value;
        }
    }
}
PHP);

        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([new NoAssignmentInCondition()]);
PHP);

        return [
            'dir'  => $dir,
            'php'  => $php,
            'soda' => $soda,
        ];
    }

    /**
     * @param array{dir: string, php: string, soda: string} $project
     */
    private function cleanupAssignmentInConditionProject(array $project): void
    {
        foreach (['php', 'soda'] as $key) {
            if (is_file($project[$key])) {
                unlink($project[$key]);
            }
        }

        if (is_dir($project['dir'])) {
            rmdir($project['dir']);
        }
    }
}
