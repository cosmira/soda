<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Cosmira\Soda\Reporting\QualityJsonReportFormatter;
use Cosmira\Soda\Reporting\RuleMetadata;
use Cosmira\Soda\Rules\Complexity\MaxConsecutiveLocalAssignments;
use Cosmira\Soda\Rules\Complexity\NoRepeatedStringLiterals;
use Cosmira\Soda\Rules\Complexity\NoReturnInFinally;
use Cosmira\Soda\Rules\Complexity\NoUnneededTernary;
use Cosmira\Soda\Rules\Usage\NoReassignedParameters;
use PHPUnit\Framework\TestCase;

final class ExpressionAndLocalStateIntegrationTest extends TestCase
{
    public function testCatalogAndGeneratedConfigEnableAllFiveRules(): void
    {
        $init = SodaInitFileEmitter::emit();
        foreach ([new NoUnneededTernary, new NoReturnInFinally, new NoReassignedParameters, new MaxConsecutiveLocalAssignments, new NoRepeatedStringLiterals] as $rule) {
            self::assertArrayHasKey($rule->id(), RuleCatalog::standardDefinitions());
            self::assertStringContainsString('new '.(new \ReflectionClass($rule))->getShortName().'(', $init);
        }
        self::assertStringContainsString('new MaxConsecutiveLocalAssignments(8)', $init);
        self::assertStringContainsString('new NoRepeatedStringLiterals(3)', $init);
    }

    public function testStandardRunnerEnforcesAllFiveDefaults(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-five-defaults-');
        file_put_contents($path, <<<'PHP'
<?php
class Worker {
    function run($input) {
        $a=1; $b=2; $c=3; $d=4; $e=5; $f=6; $g=7; $h=8; $i=9;
        $input = normalize($input);
        send('ready'); send('ready'); send('ready');
        try { return $input ? true : false; }
        finally { return false; }
    }
}
PHP);

        try {
            $ids = ['no_unneeded_ternary', 'no_return_in_finally', 'no_reassigned_parameters', 'max_consecutive_local_assignments', 'no_repeated_string_literals'];
            $result = (new Runner)->check([$path], Soda::configure()->with(RuleCatalog::standard())->checks());
            $findings = $result->violations->filter(static fn ($finding): bool => in_array($finding->rule, $ids, true))->values()->all();
            self::assertSame($ids, array_column($findings, 'rule'));
            self::assertSame([0, 0, 0, 8, 2], array_column($findings, 'threshold'));
            self::assertSame([1, 1, 1, 9, 3], array_column($findings, 'value'));
        } finally {
            unlink($path);
        }
    }

    public function testFullAndSelectiveFactsAndRunnerProduceIdenticalEvidence(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-five-integration-');
        file_put_contents($path, <<<'PHP'
<?php
namespace App;
class Worker {
    function run($input) {
        $a = 1; $b = 2; $c = 3;
        $input = normalize($input);
        send('ready'); send('ready'); send('ready');
        try { return $input ? true : false; }
        finally { return false; }
    }
}
PHP);

        try {
            $rules = [new NoUnneededTernary, new NoReturnInFinally, new NoReassignedParameters, new MaxConsecutiveLocalAssignments(2), new NoRepeatedStringLiterals];
            $expected = [];
            foreach ($rules as $rule) {
                self::assertSame([], $rule->requiredAnalyses());
                $full = (new FactCollector)->collect($path);
                $selective = (new FactCollector)->collect($path, []);
                $normalize = static fn ($finding): array => $finding->toArray();
                $findings = array_map($normalize, iterator_to_array($rule->checkFile($selective)));
                self::assertSame(array_map($normalize, iterator_to_array($rule->checkFile($full))), $findings);
                self::assertCount(1, $findings);
                self::assertSame($path, $findings[0]['file']);
                self::assertGreaterThan(0, $findings[0]['line']);
                self::assertNotEmpty($findings[0]['message']);
                array_push($expected, ...$findings);
            }
            $result = (new Runner)->check([$path], Soda::configure()->with($rules)->checks());
            self::assertSame($expected, $result->violations->map(static fn ($finding): array => $finding->toArray())->all());
            $report = (new QualityJsonReportFormatter(new RuleMetadata(RuleCatalog::definitions())))->format($result);
            $json = json_decode(json_encode($report, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            self::assertFalse($json['passed']);
            self::assertSame(4, $json['schema_version']);
            self::assertCount(5, $json['violations']);
            foreach ($json['violations'] as $row) {
                self::assertSame(RuleCatalog::definitions()[$row['rule']]['advice'], $row['recommendation']);
                unset($row['recommendation']);
                self::assertContains($row, $expected);
            }
        } finally {
            unlink($path);
        }
    }

    public function testDocumentationConfigurationIsExecutable(): void
    {
        $docs = file_get_contents(dirname(__DIR__, 2).'/docs/EXPRESSION_AND_LOCAL_STATE_RULES.md');
        $docs = str_replace("\r\n", "\n", (string) $docs);
        self::assertSame(1, preg_match('/```php\n(.*?)\n```/s', $docs, $matches));
        $path = tempnam(sys_get_temp_dir(), 'soda-five-doc-config-');
        file_put_contents($path, '<?php '.$matches[1]);

        try {
            $config = require $path;
            self::assertCount(109, $config->checks());
            self::assertSame([dirname($path).'/src'], $config->paths());
        } finally {
            unlink($path);
        }
    }

    public function testLegitimateStatefulCodePassesAllFivePolicies(): void
    {
        $source = <<<'PHP'
<?php
class Builder {
    private const READY = 'ready';
    private array $items = [];
    function __construct(private object $client) {}
    function add(object $item, array $options): self {
        $this->items[] = $item;
        $options['enabled'] = true;
        $item->enabled = true;
        return $this;
    }
    function run(array $items): bool {
        $count = count($items);
        $enabled = (bool) $count;
        foreach ($items as $item) { $this->client->send($item, self::READY); }
        try { return $enabled; }
        finally { $this->client->close(); }
    }
}
PHP;
        foreach ([new NoUnneededTernary, new NoReturnInFinally, new NoReassignedParameters, new MaxConsecutiveLocalAssignments, new NoRepeatedStringLiterals] as $rule) {
            self::assertSame([], FiveRuleFixture::findings($rule, $source), $rule->id());
        }
    }
}
