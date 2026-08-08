<?php

declare(strict_types=1);
/*
 * This file is part of Soda.
 *
 * (c) Bunnivo
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bunnivo\Soda\Commands;

use Bunnivo\Soda\Application;
use Bunnivo\Soda\ComplexityMetrics;
use Bunnivo\Soda\CoreMetrics;
use Bunnivo\Soda\LocMetrics;
use Bunnivo\Soda\Quality\Engine\QualityAnalysisContract;
use Bunnivo\Soda\Quality\QualityResult;
use Bunnivo\Soda\Result;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class QualityCommandTest extends TestCase
{
    /** REMOVE_WHEN sebastian/complexity adds Enum support (see EnumAwareComplexityVisitorTest) */
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

    public function testReportJsonIncludesMetricsAndViolations(): void
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
        $this->assertSame(2, $data['schema_version']);
        $this->assertArrayNotHasKey('score', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('violations', $data);
        $this->assertArrayHasKey('directories', $data['metrics']);
        $this->assertArrayHasKey('loc', $data['metrics']);
    }

    public function testUsesInjectedQualityAnalyser(): void
    {
        $result = new QualityResult($this->minimalProjectResult(), collect([]));

        $stub = new readonly class($result) implements QualityAnalysisContract
        {
            public function __construct(private QualityResult $out) {}

            #[\Override]
            public function analyse(array $files, bool $debug, ?string $configPath = null): QualityResult
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

use Bunnivo\Soda\Config\Soda;

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

use Bunnivo\Soda\Config\Soda;

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
        mkdir($dir.'/app/Services', 0700, true);
        $soda = $dir.'/soda.php';
        file_put_contents($soda, <<<'PHP'
<?php

declare(strict_types=1);

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxLayerDominancePercentage;

return Soda::configure()
    ->withPaths(['app/Services'])
    ->with([new MaxLayerDominancePercentage(50, 4)]);
PHP);

        foreach (range(1, 5) as $index) {
            file_put_contents($dir.'/app/Services/User'.$index.'.php', "<?php\n\nnamespace App\\Services;\n\nclass User{$index} extends UserService {}\n");
        }

        foreach (range(1, 2) as $index) {
            file_put_contents($dir.'/app/Services/Controller'.$index.'.php', "<?php\n\nnamespace App\\Services;\n\nclass Controller{$index} extends Controller {}\n");
        }

        file_put_contents($dir.'/app/Services/Plain.php', "<?php\n\nnamespace App\\Services;\n\nclass Plain {}\n");

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
            $this->assertStringContainsString('UserService dominates 62.5%', $text);
        } finally {
            foreach (glob($dir.'/app/Services/*.php') ?: [] as $file) {
                unlink($file);
            }

            unlink($soda);
            rmdir($dir.'/app/Services');
            rmdir($dir.'/app');
            rmdir($dir);
        }
    }

    private function minimalProjectResult(): Result
    {
        $loc = new LocMetrics([
            'directories'           => 1,
            'files'                 => 1,
            'linesOfCode'           => 10,
            'commentLinesOfCode'    => 0,
            'nonCommentLinesOfCode' => 10,
            'logicalLinesOfCode'    => 5,
        ]);
        $complexity = new ComplexityMetrics([
            'functions'       => 0,
            'funcLowest'      => 1,
            'funcAverage'     => 1.0,
            'funcHighest'     => 1,
            'classesOrTraits' => 1,
            'methods'         => 1,
            'methodLowest'    => 1,
            'methodAverage'   => 1.0,
            'methodHighest'   => 1,
        ]);

        return new Result([], new CoreMetrics($loc, $complexity));
    }
}
