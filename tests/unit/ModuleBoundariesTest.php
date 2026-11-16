<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use ArgumentCountError;
use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\ModuleBoundaries;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleBoundariesTest extends TestCase
{
    public function testNamespaceIsRequired(): void
    {
        $this->expectException(ArgumentCountError::class);
        new ModuleBoundaries;
    }

    public function testMultipleRootsKeepTheirOwnPolicy(): void
    {
        $config = Soda::configure()->with([
            new ModuleBoundaries('App\\ModulesAgent', except: ['Access']),
            new ModuleBoundaries('App\\ModulesClient'),
        ]);
        $path = tempnam(sys_get_temp_dir(), 'soda-module-roots-');
        file_put_contents($path, <<<'PHP'
<?php
namespace App\ModulesAgent\Audit;
class AgentUser extends \App\ModulesAgent\Access\Models\User {}
class AgentPerson extends \App\ModulesAgent\Directory\Models\Person {}
namespace App\ModulesClient\Audit;
class ClientUser extends \App\ModulesClient\Access\Models\User {}
class ClientDirectory extends \App\ModulesClient\Directory\Facades\Directory {}
class CrossRoot extends \App\ModulesAgent\Directory\Models\Person {}
PHP);

        try {
            $result = (new Runner)->check([$path], $config->checks());
            self::assertSame([
                'App\\ModulesAgent\\Audit\\AgentPerson',
                'App\\ModulesClient\\Audit\\ClientUser',
            ], $result->violations->pluck('class')->all());
        } finally {
            unlink($path);
        }
    }

    public function testSodaChecksItsRuleCategoryBoundaries(): void
    {
        $config = require __DIR__.'/../../soda.php';
        $rules = array_values(array_filter($config->checks(), fn ($rule) => $rule instanceof ModuleBoundaries));
        self::assertCount(1, $rules);
        self::assertCount(1, $this->findings($rules[0], '<?php namespace Cosmira\\Soda\\Rules\\Architecture; class Consumer extends \\Cosmira\\Soda\\Rules\\Naming\\VariableNameLength {}'));
        self::assertSame([], $this->findings($rules[0], '<?php namespace Cosmira\\Soda\\Rules\\Architecture; class Consumer extends \\Cosmira\\Soda\\Contracts\\Check {}'));
    }

    #[DataProvider('dependencies')]
    public function testModuleAccess(string $source, string $target, int $expected): void
    {
        $rule = new ModuleBoundaries('App\\Modules', except: ['Access']);
        $findings = $this->findings($rule, "<?php namespace $source; class Consumer { function run(\\$target \$input) {} }");
        self::assertCount($expected, $findings);
        if ($expected === 1) {
            self::assertSame('module_boundaries', $findings[0]->rule);
            self::assertSame($source.'\\Consumer', $findings[0]->class);
            self::assertSame(1, $findings[0]->line);
            self::assertStringContainsString($target, $findings[0]->message);
        }
    }

    public static function dependencies(): iterable
    {
        yield 'private model' => ['App\\Modules\\Audit', 'App\\Modules\\Directory\\Models\\Person', 1];
        yield 'module root type' => ['App\\Modules\\Audit', 'App\\Modules\\Directory\\Person', 1];
        yield 'same module' => ['App\\Modules\\Directory\\Actions', 'App\\Modules\\Directory\\Models\\Person', 0];
        yield 'event' => ['App\\Modules\\Audit', 'App\\Modules\\Directory\\Events\\Nested\\Changed', 0];
        yield 'facade' => ['App\\Modules\\Directory', 'App\\Modules\\Audit\\Facades\\Audit', 0];
        yield 'similar export' => ['App\\Modules\\Audit', 'App\\Modules\\Directory\\EventsExtra\\Changed', 1];
        yield 'nested export name is private' => ['App\\Modules\\Audit', 'App\\Modules\\Directory\\Internal\\Events\\Changed', 1];
        yield 'similar modules are distinct' => ['App\\Modules\\DirectoryExtra', 'App\\Modules\\Directory\\Models\\Person', 1];
        yield 'excluded target' => ['App\\Modules\\Audit', 'App\\Modules\\Access\\Models\\User', 0];
        yield 'excluded module still respects other boundaries' => ['App\\Modules\\Access', 'App\\Modules\\Directory\\Models\\Person', 1];
        yield 'similar exception is private' => ['App\\Modules\\Audit', 'App\\Modules\\AccessExtra\\Models\\User', 1];
        yield 'case insensitive' => ['APP\\MODULES\\AUDIT', 'app\\modules\\directory\\facades\\Directory', 0];
        yield 'external dependency' => ['App\\Modules\\Audit', 'Illuminate\\Support\\Collection', 0];
        yield 'external caller' => ['App\\Console', 'App\\Modules\\Directory\\Models\\Person', 0];
        yield 'similar root' => ['App\\ModulesExtra\\Audit', 'App\\Modules\\Directory\\Models\\Person', 0];
    }

    public function testResolvesAliasesAndReportsEachDependencyOnce(): void
    {
        $findings = $this->findings(new ModuleBoundaries('App\\Modules'), <<<'PHP'
<?php
namespace App\Modules\Audit;
use App\Modules\Directory\Models\Person as Employee;
use App\Modules\Directory\Models\UnusedImport;
trait Consumer {
    function run(Employee $person): Employee { return new Employee; }
}
PHP);
        self::assertCount(1, $findings);
        self::assertStringContainsString('App\\Modules\\Directory\\Models\\Person', $findings[0]->message);
        self::assertSame(5, $findings[0]->line);
    }

    #[DataProvider('typeDeclarations')]
    public function testEveryNamedTypeRespectsModuleBoundaries(string $declaration): void
    {
        $source = '<?php namespace App\\Modules\\Audit; '.$declaration;
        $findings = $this->findings(new ModuleBoundaries('App\\Modules'), $source);
        self::assertCount(1, $findings);
        self::assertSame('App\\Modules\\Audit\\Consumer', $findings[0]->class);
    }

    public static function typeDeclarations(): iterable
    {
        yield 'interface parent' => ['interface Consumer extends \\App\\Modules\\Directory\\Contracts\\Person {}'];
        yield 'interface signature' => ['interface Consumer { function person(): \\App\\Modules\\Directory\\Models\\Person; }'];
        yield 'enum implementation' => ['enum Consumer implements \\App\\Modules\\Directory\\Contracts\\Person {}'];
        yield 'enum case value' => ['enum Consumer: string { case Person = \\App\\Modules\\Directory\\Models\\Person::TYPE; }'];
        yield 'enum method' => ['enum Consumer { function person() { return new \\App\\Modules\\Directory\\Models\\Person; } }'];
    }

    public function testCustomRootAndExports(): void
    {
        $rule = new ModuleBoundaries(namespace: '\\Domain\\', exports: ['\\Contracts\\']);
        self::assertSame([], $this->findings($rule, '<?php namespace Domain\\Sales; class Order extends \\Domain\\Billing\\Contracts\\Invoice {}'));
        self::assertCount(1, $this->findings($rule, '<?php namespace Domain\\Sales; class Order extends \\Domain\\Billing\\Events\\Invoice {}'));
    }

    public function testEmptyExportsKeepAllForeignTypesPrivate(): void
    {
        self::assertCount(1, $this->findings(new ModuleBoundaries('App\\Modules', exports: []), '<?php namespace App\\Modules\\Audit; class Consumer extends \\App\\Modules\\Directory\\Facades\\Directory {}'));
    }

    #[DataProvider('invalidPrefixes')]
    public function testRejectsEmptyPrefixes(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ModuleBoundaries(...['namespace' => 'App\\Modules', ...$arguments]);
    }

    public static function invalidPrefixes(): iterable
    {
        yield [['namespace' => '']];
        yield [['namespace' => '\\']];
        yield [['exports' => ['']]];
        yield [['except' => ['\\']]];
    }

    public function testCatalogKeepsRuleOptIn(): void
    {
        $entry = RuleCatalog::definitions()['module_boundaries'];
        $rule = new $entry['class'](...$entry['arguments']);
        self::assertSame('module_boundaries', $rule->id());
        self::assertArrayNotHasKey('module_boundaries', RuleCatalog::standardDefinitions());
    }

    private function findings(ModuleBoundaries $rule, string $source): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-modules-');
        file_put_contents($path, $source);

        try {
            $facts = (new FactCollector)->collect($path, $rule->requiredAnalyses());

            return iterator_to_array($rule->checkFile($facts));
        } finally {
            unlink($path);
        }
    }
}
