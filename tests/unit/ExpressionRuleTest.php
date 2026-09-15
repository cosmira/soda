<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Runner as QualityAnalyser;
use Cosmira\Soda\Config\ConfigException;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check as SodaRule;
use Cosmira\Soda\Rules\ExpressionCheck;
use PHPUnit\Framework\TestCase;

final class ExpressionRuleTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'soda-rule-');
        file_put_contents($this->file, <<<'PHP'
<?php
namespace App\Domain;
use Illuminate\Database\Model as Record;
final class Order {
    public function save(Record $record, int $value): int {
        if ($value > 1) {
            if ($value > 2) { return 3; }
        }
        return 0;
    }
}
PHP);
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testSelectiveAnalysisMatchesFullAnalysisIncludingLocations(): void
    {
        $rules = [
            new ExpressionProbe('large_file', 'file where loc > 1'),
            new ExpressionProbe('complex_method', 'method where complexity > 2 and nesting > 1', 'Simplify this method.'),
            new ExpressionProbe('domain_boundary', 'class where namespace matches "App\\\\Domain*" and depends_on matches "Illuminate\\\\*"'),
            new ExpressionProbe('file_size', 'file where loc >= 0'),
        ];
        $required = array_unique(array_merge(...array_map(fn ($rule) => $rule->requiredAnalyses(), $rules)));
        $full = CheckFixture::collect($this->file, $rules);
        $selected = CheckFixture::collect($this->file, $rules, $required);
        $this->assertEquals($full['violations'], $selected['violations']);
        $this->assertCount(4, $selected['violations']);
        $method = $selected['violations'][1];
        $this->assertSame(5, $method->line);
        $this->assertSame('App\\Domain\\Order::save', $method->method);
        $this->assertSame('App\\Domain\\Order', $method->class);
        $this->assertSame('Simplify this method.', $method->toArray()['message']);
        $this->assertSame(4, $selected['violations'][2]->line);
    }

    public function testUnrequestedFactsAreNotCollected(): void
    {
        $file = (new FactCollector)->collect($this->file, ['complexity']);
        $method = $file->metrics['methods']['App\\Domain\\Order::save'];
        $this->assertSame(3, $method['complexity']);
        $this->assertSame(0, $method['nesting']);
        $this->assertSame(0, $method['returns']);
        $this->assertSame([], $file->metrics['naming']);
        $this->assertSame(0, $file->metrics['classes']['App\\Domain\\Order']['efferent_coupling']);
    }

    public function testLegacyRulesKeepAllAnalyses(): void
    {
        $legacy = new class extends SodaRule
        {
            public function id(): string
            {
                return 'legacy';
            }

            protected function evaluate(string $file, array $metrics): array
            {
                return [];
            }
        };
        $this->assertNull($legacy->requiredAnalyses());
    }

    public function testConfigurationRunsRulesAndReportsBrokenSources(): void
    {
        $config = $this->file.'.php';
        file_put_contents($config, <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()->with([
    new \Cosmira\Soda\Rules\Structure\MaxArguments(1),
]);
PHP);

        try {
            $analyser = new QualityAnalyser;
            $result = $analyser->analyse([$this->file], $config);
            $this->assertFalse($result->isPassing());
            file_put_contents($this->file, '<?php function broken(');
            $result = $analyser->analyse([$this->file], $config);
            $this->assertFalse($result->isPassing());
            $this->assertSame('parse_error', $result->violations->first()->rule);
            // Reusing the analyser after a parse failure must not retain old AST state.
            file_put_contents($this->file, '<?php function valid(): void {}');
            $this->assertTrue($analyser->analyse([$this->file], $config)->isPassing());
        } finally {
            unlink($config);
        }
    }

    public function testUnknownFieldReportsRuleId(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Rule 'bad_rule': Rule at line 1");
        new ExpressionProbe('bad_rule', 'method where typo > 2');
    }

    public function testEnumMethodsHaveTheirOwnFactsAndQualifiedNames(): void
    {
        file_put_contents($this->file, <<<'PHP'
<?php
namespace App;
enum First {
    case One;
    public function run(int $n): int {
        if ($n > 0) {
            if ($n > 1) { return 2; }
        }
        return 0;
    }
}
enum Second {
    case Two;
    public function run(int $n): int { return $n; }
}
PHP);
        $rule = new ExpressionProbe('enum_flow', 'method where complexity == 3 and nesting == 2 and returns == 2');
        $full = CheckFixture::collect($this->file, [$rule]);
        $selected = CheckFixture::collect($this->file, [$rule], $rule->requiredAnalyses());
        $this->assertEquals($full['violations'], $selected['violations']);
        $this->assertCount(1, $selected['violations']);
        $this->assertSame('App\\First::run', $selected['violations'][0]->method);
        $this->assertSame(1, $selected['metrics']['methods']['App\\Second::run']['complexity']);
        $this->assertArrayNotHasKey('unknown::run', $selected['metrics']['methods']);
    }

    public function testNestedAndTopLevelStatementsKeepCallableOwnership(): void
    {
        file_put_contents($this->file, <<<'PHP'
<?php
function outer() {
    $a = function () {
        $b = function () { if (true) { return 1; } };
        try {} catch (\Throwable $e) {}
        if (true) { return 2; }
    };
    function inner() {
        if (true) { if (true) { return 1; } }
        try {} catch (\Throwable $e) {}
        return 2;
    }
    if (true) { return 3; }
    try {} catch (\Throwable $e) {}
}
return 4;
try {} catch (\Throwable $e) {}
PHP);
        $rule = new ExpressionProbe('scope_probe', 'method where returns >= 0 and try_catch >= 0 and nesting >= 0 and complexity >= 1');
        $full = CheckFixture::collect($this->file, [$rule]);
        $selected = CheckFixture::collect($this->file, [$rule], $rule->requiredAnalyses());
        $this->assertEquals($full['violations'], $selected['violations']);
        $outer = $selected['metrics']['methods']['outer'];
        $inner = $selected['metrics']['methods']['inner'];
        $this->assertSame(1, $outer['returns']);
        $this->assertSame(2, $inner['returns']);
        $this->assertSame(1, $outer['try_catch']);
        $this->assertSame(1, $inner['try_catch']);
        $this->assertSame(1, $outer['nesting']);
        $this->assertSame(2, $inner['nesting']);
        $this->assertSame(3, $outer['complexity']);
        $this->assertSame(4, $inner['complexity']);
    }

    public function testAttributeDependenciesAreResolvedAtEveryDeclaration(): void
    {
        file_put_contents($this->file, <<<'PHP'
<?php
namespace App;
use Illuminate\ClassMarker as Marker;
#[Marker]
class Annotated {
    #[\Illuminate\PropertyMarker]
    public string $value;
    #[\Illuminate\MethodMarker]
    public function run(#[\Illuminate\ParameterMarker] string $name): void {}
}
PHP);
        $rule = new ExpressionProbe('no_framework_attributes', 'class where depends_on matches "Illuminate\\\\*"');
        $full = CheckFixture::collect($this->file, [$rule]);
        $selected = CheckFixture::collect($this->file, [$rule], $rule->requiredAnalyses());
        $this->assertEquals($full['violations'], $selected['violations']);
        $this->assertCount(1, $selected['violations']);
        $this->assertSame([
            'Illuminate\\ClassMarker', 'Illuminate\\PropertyMarker',
            'Illuminate\\MethodMarker', 'Illuminate\\ParameterMarker',
        ], $selected['metrics']['classes']['App\\Annotated']['depends_on']);
    }
}

/** Tests the author-facing expression contract, not a public string configuration API. */
final class ExpressionProbe extends ExpressionCheck
{
    public function __construct(private string $identifier, private string $condition, private ?string $message = null)
    {
        $this->requiredAnalyses();
    }

    public function id(): string
    {
        return $this->identifier;
    }

    protected function expression(): string
    {
        return $this->condition;
    }

    protected function violation(FileFacts $file, array $row): Violation
    {
        $name = $row['name'] ?? null;
        $isMethod = str_starts_with($this->condition, 'method ');
        $class = $isMethod && str_contains($name, '::') ? strstr($name, '::', true) : $name;
        $field = preg_match('/where (\w+) [><=]+ ([0-9]+)/', $this->condition, $matches) ? $matches[1] : null;
        $value = $field === null ? count($row['depends_on'] ?? []) : (int) $row[$field];
        $threshold = $field === null ? 0 : (int) $matches[2];

        return new Violation(
            rule: $this->id(), file: $file->path, value: $value, threshold: $threshold,
            method: $isMethod ? $name : null, class: $class, line: $row['line'] ?? null, message: $this->message,
        );
    }
}
