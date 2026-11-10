<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Rules\Architecture\ForbiddenDependencies;
use Cosmira\Soda\Rules\Complexity\MaxStatementsPerCallable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdditionalMaintainabilityRulesTest extends TestCase
{
    public function testDirectedBoundaryWorksWithoutACycleAndResolvesAliases(): void
    {
        $source = <<<'PHP'
<?php
namespace App\Analysis;
use App\Commands\Run as Command;
class Worker {
    public function run(Command $input): Command { return new Command; }
}
PHP;
        $findings = $this->findings(new ForbiddenDependencies(['App\Analysis' => ['App\Commands']]), $source);
        self::assertCount(1, $findings);
        self::assertSame('forbidden_dependencies', $findings[0]->rule);
        self::assertSame('App\Analysis\Worker', $findings[0]->class);
        self::assertSame(4, $findings[0]->line);
        self::assertSame(1, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
        self::assertStringContainsString('App\Analysis\Worker -> App\Commands\Run', $findings[0]->message);
        self::assertSame([], $this->findings(new ForbiddenDependencies(['App\Commands' => ['App\Analysis']]), $source));
        self::assertSame([], $this->findings(new ForbiddenDependencies, $source));
    }

    public function testNamespaceBoundariesAndUnusedImports(): void
    {
        $rule = new ForbiddenDependencies(['\\app\\analysis\\' => ['\\APP\\COMMANDS\\']]);
        self::assertCount(1, $this->findings($rule, '<?php namespace App\Analysis\Nested; trait Worker { function run(\App\Commands\Run $input) {} }'));
        self::assertSame([], $this->findings($rule, '<?php namespace App\AnalysisExtra; class Worker { function run(\App\Commands\Run $input) {} }'));
        self::assertSame([], $this->findings($rule, '<?php namespace App\Analysis; class Worker { function run(\App\CommandsExtra\Run $input) {} }'));
        self::assertSame([], $this->findings($rule, '<?php namespace App\Analysis; use App\Commands\Run; class Worker {}'));
    }

    public function testExactTypesAndGlobalYiiClassesRespectDependencyBoundaries(): void
    {
        $rule = new ForbiddenDependencies(['App\Schema' => ['CActiveRecord', 'Yii', 'App\Runtime\Gateway']]);
        $source = '<?php namespace App\Schema; class Record extends \CActiveRecord { function run(\App\Runtime\Gateway $gateway) { \Yii::app(); } }';

        self::assertCount(3, $this->findings($rule, $source));
        self::assertCount(1, $this->findings(new ForbiddenDependencies(['App\Schema\Record' => ['Yii']]), $source));
        self::assertSame([], $this->findings($rule, '<?php namespace App\Schema; class Record extends \CActiveRecordExtra {}'));
    }

    public function testOverlappingRestrictionsReportEachTargetOnce(): void
    {
        $rule = new ForbiddenDependencies(['App' => ['App\Commands'], 'App\Analysis' => ['App\Commands', 'App\Commands\Nested']]);
        $source = '<?php namespace App\Analysis; class Worker extends \App\Commands\Nested\Base { function run() { \App\Commands\Other::run(); } }';
        self::assertCount(2, $this->findings($rule, $source));
    }

    #[DataProvider('statementBodies')]
    public function testStatementCounting(string $body, int $expected): void
    {
        $source = '<?php function work() { '.$body.' }';
        $findings = $this->findings(new MaxStatementsPerCallable(0), $source);
        if ($expected === 0) {
            self::assertSame([], $findings);

            return;
        }

        self::assertCount(1, $findings);
        self::assertSame($expected, $findings[0]->value);
        self::assertSame([], $this->findings(new MaxStatementsPerCallable($expected), $source));
        self::assertCount(1, $this->findings(new MaxStatementsPerCallable($expected - 1), $source));
    }

    public static function statementBodies(): array
    {
        return [
            'comments and empty statements' => ['/* explain */ ;', 0],
            'straight line'                 => ['$a = 1; execute($a); return;', 3],
            'multiline expression'          => ["execute(\n1,\n2\n);", 1],
            'branches'                      => ['if ($a) { first(); } elseif ($b) { second(); } else { third(); }', 4],
            'loops'                         => ['foreach ($items as $item) { while ($item) { break; } }', 3],
            'switch wrappers'               => ['switch ($value) { case 1: first(); break; default: return; }', 4],
            'exception wrappers'            => ['try { first(); } catch (\Exception $e) { second(); } finally { third(); }', 4],
        ];
    }

    public function testNestedCallablesAndAnonymousClassMethodsAreSeparate(): void
    {
        $source = <<<'PHP'
<?php
namespace App;
function work() {
    $callback = function () { first(); second(); third(); };
    $object = new class { function run() { first(); second(); third(); } };
    function nested() { first(); second(); third(); }
}
PHP;
        $findings = $this->findings(new MaxStatementsPerCallable(2), $source);
        self::assertCount(3, $findings);
        self::assertSame(['{closure@4}', '{anonymous-class}::run', 'App\nested'], array_column($findings, 'method'));
        self::assertSame([3, 3, 3], array_column($findings, 'value'));
    }

    public function testMethodsFunctionsAndArrowLocations(): void
    {
        $source = "<?php\nnamespace App;\ntrait Worker { function run() { first(); second(); } }\n\$callback = fn () => first();\ninterface Contract { function run(); }";
        $findings = $this->findings(new MaxStatementsPerCallable(0), $source);
        self::assertCount(2, $findings);
        self::assertSame('App\Worker::run', $findings[0]->method);
        self::assertSame('App\Worker', $findings[0]->class);
        self::assertSame(3, $findings[0]->line);
        self::assertSame('max_statements_per_callable', $findings[0]->rule);
        self::assertSame(0, $findings[0]->threshold);
        self::assertSame(1, $findings[1]->value);
        self::assertSame(4, $findings[1]->line);
        self::assertNull($findings[1]->class);
    }

    public function testRejectsNegativeStatementLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MaxStatementsPerCallable(-1);
    }

    #[DataProvider('emptyPrefixes')]
    public function testRejectsEmptyNamespacePrefixes(array $mapping): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ForbiddenDependencies($mapping);
    }

    public static function emptyPrefixes(): array
    {
        return [[['' => ['App']]], [['App' => ['\\']]]];
    }

    public function testCatalogEnablesStatementLimitAndKeepsDependenciesOptional(): void
    {
        self::assertArrayHasKey('max_statements_per_callable', RuleCatalog::standardDefinitions());
        self::assertArrayNotHasKey('forbidden_dependencies', RuleCatalog::standardDefinitions());
        foreach (['forbidden_dependencies', 'max_statements_per_callable'] as $id) {
            self::assertArrayHasKey($id, RuleCatalog::definitions());
            $definition = RuleCatalog::definitions()[$id];
            self::assertSame($id, (new $definition['class'](...$definition['arguments']))->id());
        }
    }

    public function testStandardRunnerEnforcesTwentyStatements(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-statements-standard-');

        try {
            foreach ([20, 21] as $count) {
                file_put_contents($path, '<?php function work() { '.str_repeat('execute();', $count).' }');
                $result = (new Runner)->check([$path], Soda::configure()->with(RuleCatalog::standard())->checks());
                $findings = $result->violations->filter(fn ($finding): bool => $finding->rule === 'max_statements_per_callable')->values()->all();
                self::assertCount($count === 20 ? 0 : 1, $findings);
                if ($count === 21) {
                    self::assertSame(21, $findings[0]->value);
                    self::assertSame(20, $findings[0]->threshold);
                }
            }
        } finally {
            unlink($path);
        }
    }

    public function testRunnerAndJsonKeepEvidenceForBothChecks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-maintainability-');
        file_put_contents($path, '<?php namespace App\Analysis; class Worker { function run(\App\Commands\Run $input) { first(); second(); } }');

        try {
            $config = Soda::configure()->with([
                new ForbiddenDependencies(['App\Analysis' => ['App\Commands']]),
                new MaxStatementsPerCallable(1),
            ]);
            $result = (new Runner)->check([$path], $config->checks());
            self::assertCount(2, $result->violations);
            foreach ($result->violations as $finding) {
                self::assertSame($path, $finding->toArray()['file']);
                self::assertSame(1, $finding->toArray()['line']);
                self::assertNotEmpty($finding->toArray()['message']);
            }
        } finally {
            unlink($path);
        }
    }

    private function findings(Check $rule, string $source): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-maintainability-');
        file_put_contents($path, $source);

        try {
            $facts = (new FactCollector)->collect($path, $rule->requiredAnalyses());

            return iterator_to_array($rule->checkFile($facts));
        } finally {
            unlink($path);
        }
    }
}
