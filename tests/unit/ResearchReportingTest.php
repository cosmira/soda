<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Application;
use Cosmira\Soda\Commands\ListRulesCommand;
use Cosmira\Soda\Commands\QualityCommand;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaConfig;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Contracts\ResearchCheck;
use Cosmira\Soda\Rules\Design\RepresentationChurn;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ResearchReportingTest extends TestCase
{
    public function testRunnerAggregatesResearchWithoutViolations(): void
    {
        $report = $this->report();
        $check = new class($report) extends ResearchCheck
        {
            public function __construct(private array $report) {}

            public function id(): string
            {
                return 'representation_churn';
            }

            public function requiredAnalyses(): ?array
            {
                return [];
            }

            public function researchProject(ProjectFacts $project): array
            {
                return $this->report;
            }
        };
        $result = (new Runner)->check([], (new SodaConfig)->with([$check])->checks());

        $this->assertTrue($result->isPassing());
        $this->assertSame(['representation_churn' => $report], $result->research);
        $this->assertSame([], $check->checkProject(new ProjectFacts));
    }

    public function testRunnerKeepsOrdinaryProjectViolationsGating(): void
    {
        $research = new class extends ResearchCheck
        {
            public function id(): string
            {
                return 'research';
            }

            public function researchProject(ProjectFacts $project): array
            {
                return ['candidates' => [['status' => 'structural']]];
            }
        };
        $ordinary = new class extends Check
        {
            public function id(): string
            {
                return 'ordinary';
            }

            public function checkProject(ProjectFacts $project): iterable
            {
                return [new Violation(rule: 'ordinary', file: '/project/Example.php', value: 1, threshold: 0)];
            }
        };
        $result = (new Runner)->check([], (new SodaConfig)->with([$research, $ordinary])->checks());

        $this->assertFalse($result->isPassing());
        $this->assertCount(1, $result->violations);
        $this->assertSame('ordinary', $result->violations->first()->rule);
        $this->assertCount(1, $result->research['research']['candidates']);
    }

    public function testEmptyResearchSerializesAsObject(): void
    {
        $data = (new ResearchJsonReportFormatter)->format(new QualityResult([]));
        $this->assertSame('{"schema_version":1,"research":{}}', json_encode($data));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testCliSeparatesResearchAndPreservesQualityGate(bool $violation): void
    {
        $result = new QualityResult(
            $violation ? [new Violation(rule: 'actual_rule', file: '/project/Example.php', value: 1, threshold: 0, message: 'Actual quality violation.')] : [],
            ['representation_churn' => $this->report()],
        );
        $stub = new class($result) extends Runner
        {
            public function __construct(private QualityResult $result) {}

            public function check(array $files, array $checks): QualityResult
            {
                return $this->result;
            }
        };
        $dir = sys_get_temp_dir().'/soda-research-report-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/Example.php', '<?php');
        file_put_contents($dir.'/soda.php', '<?php return (new \\Cosmira\\Soda\\Config\\SodaConfig)->with([]);');
        $quality = $dir.'/quality.json';
        $research = $dir.'/research.json';

        try {
            $container = new Application;
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add(new QualityCommand($stub));
            $input = new ArrayInput([
                'command'         => 'quality',
                'path'            => [$dir],
                '--config'        => $dir.'/soda.php',
                '--report-type'   => 'json',
                '--report-file'   => $quality,
                '--research-json' => $research,
            ]);
            $buffer = new BufferedOutput;
            $exit = $artisan->run($input, new OutputStyle($input, $buffer));
            $text = $buffer->fetch();
            $qualityData = json_decode(file_get_contents($quality), true, flags: JSON_THROW_ON_ERROR);
            $researchData = json_decode(file_get_contents($research), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame($violation ? 1 : 0, $exit);
            $this->assertSame(['schema_version', 'passed', 'violations'], array_keys($qualityData));
            $this->assertSame(4, $qualityData['schema_version']);
            $this->assertSame(! $violation, $qualityData['passed']);
            $this->assertCount($violation ? 1 : 0, $qualityData['violations']);
            $this->assertSame(1, $researchData['schema_version']);
            $this->assertSame($result->research, $researchData['research']);
            $this->assertStringNotContainsString('Research', $text);
            $this->assertStringNotContainsString('unknown', $text);
            $this->assertStringNotContainsString('truncated', $text);
            $this->assertStringNotContainsString('Example.php:7', $text);
            $this->assertStringContainsString($violation ? '[FAIL] 1 issue' : '[OK] No issues', $text);
            if ($violation) {
                $this->assertStringContainsString('Actual quality violation.', $text);
                $this->assertStringContainsString('Example.php', $text);
            }
        } finally {
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testConsoleOmitsResearchCoverageWithoutCandidates(): void
    {
        $report = $this->report();
        $report['candidates'] = [];
        $input = new ArrayInput([]);
        $buffer = new BufferedOutput;
        (new ReportFormatter(new RuleMetadata(RuleCatalog::definitions())))->write(
            new OutputStyle($input, $buffer),
            new QualityResult([], ['representation_churn' => $report]),
            '/project',
        );
        $text = $buffer->fetch();
        $this->assertStringNotContainsString('Research', $text);
        $this->assertStringNotContainsString('0 candidates', $text);
        $this->assertStringNotContainsString('unknown', $text);
        $this->assertStringNotContainsString('truncated', $text);
        $this->assertStringContainsString('[OK]', $text);
    }

    public function testActualCrossFileResearchCliRemainsPassing(): void
    {
        $dir = sys_get_temp_dir().'/soda-research-actual-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/soda.php', '<?php return (new \Cosmira\Soda\Config\SodaConfig)->with([new \Cosmira\Soda\Rules\Design\RepresentationChurn]);');

        try {
            $container = new Application;
            $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
            $artisan->setAutoExit(false);
            $artisan->add($container->make(QualityCommand::class));
            $input = new ArrayInput([
                'command'         => 'quality',
                'path'            => [__DIR__.'/../fixtures/representation/text'],
                '--config'        => $dir.'/soda.php',
                '--report-type'   => 'json',
                '--report-file'   => $dir.'/quality.json',
                '--research-json' => $dir.'/research.json',
            ]);
            $buffer = new BufferedOutput;
            $this->assertSame(0, $artisan->run($input, new OutputStyle($input, $buffer)));
            $quality = json_decode(file_get_contents($dir.'/quality.json'), true, flags: JSON_THROW_ON_ERROR);
            $research = json_decode(file_get_contents($dir.'/research.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['schema_version' => 4, 'passed' => true, 'violations' => []], $quality);
            $this->assertSame(1, $research['schema_version']);
            $report = $research['research']['representation_churn'];
            $this->assertNotEmpty($report['candidates']);
            $this->assertSame('unknown', $report['candidates'][0]['concept_preservation']);
            $this->assertNotEmpty($report['candidates'][0]['path']);
            $text = $buffer->fetch();
            $this->assertStringNotContainsString('Research', $text);
            $this->assertStringNotContainsString('unknown', $text);
            $this->assertStringNotContainsString('Normalizer.php:', $text);
            $this->assertStringContainsString('[OK]', $text);
        } finally {
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testResearchShipsInTheStandardBundleAndGeneratedConfig(): void
    {
        $definition = RuleCatalog::definitions()['representation_churn'];
        $this->assertSame(RepresentationChurn::class, $definition['class']);
        $this->assertTrue($definition['standard']);
        $this->assertInstanceOf(ResearchCheck::class, new ($definition['class']));
        $this->assertArrayHasKey('representation_churn', RuleCatalog::standardDefinitions());
        $this->assertContains(RepresentationChurn::class, array_map(fn ($check) => $check::class, RuleCatalog::standard()));
        $this->assertStringContainsString('new RepresentationChurn(),', SodaInitFileEmitter::emit());

        $container = new Application;
        $artisan = new ConsoleApplication($container, new Dispatcher($container), '8.0');
        $artisan->setAutoExit(false);
        $artisan->add(new ListRulesCommand);
        $input = new ArrayInput(['command' => 'list:rules']);
        $buffer = new BufferedOutput;
        $this->assertSame(0, $artisan->run($input, new OutputStyle($input, $buffer)));
        $text = $buffer->fetch();
        $this->assertMatchesRegularExpression('/representation_churn[^\n]*standard/', $text);
    }

    private function report(): array
    {
        return [
            'candidates' => [['path' => [['file' => '/project/Example.php', 'line' => 7]], 'concept_preservation' => 'unknown']],
            'partial'    => [['status' => 'unknown'], ['status' => 'truncated']],
            'excluded'   => [],
            'coverage'   => ['unknown' => 1, 'truncated' => 1],
            'limits'     => [],
            'profiles'   => [],
        ];
    }
}
