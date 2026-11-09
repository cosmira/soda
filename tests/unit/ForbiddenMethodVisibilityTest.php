<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Structure\ForbiddenMethodVisibility;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ForbiddenMethodVisibilityTest extends TestCase
{
    #[DataProvider('selections')]
    public function testSelectsPathsClassesAndVisibilities(array $options, array $expected): void
    {
        $findings = $this->findings(new ForbiddenMethodVisibility(...$options));
        self::assertSame($expected, array_column($findings, 'method'));
        foreach ($findings as $finding) {
            self::assertSame('forbidden_method_visibility', $finding->rule);
            self::assertSame('app/Http/Controllers/OrderController.php', $finding->file);
            self::assertSame(1, $finding->value);
            self::assertSame(0, $finding->threshold);
            self::assertNotNull($finding->line);
            self::assertStringContainsString($finding->method.'()', $finding->message);
        }
    }

    public static function selections(): iterable
    {
        yield 'all classes and both visibilities by default' => [[], ['calculate', 'authorize', 'nested', 'inherited', 'hidden']];
        yield 'directory boundary' => [['paths' => ['app/Http/Controllers'], 'visibilities' => ['private']], ['calculate', 'nested', 'hidden']];
        yield 'file mask' => [['paths' => ['*Controller.php'], 'visibilities' => ['protected']], ['authorize', 'inherited']];
        yield 'full file' => [['paths' => ['app/Http/Controllers/OrderController.php'], 'visibilities' => ['private']], ['calculate', 'nested', 'hidden']];
        yield 'sibling directory does not match' => [['paths' => ['app/Http/Controller']], []];
        yield 'fully qualified class' => [['classes' => ['App\\Http\\OrderController']], ['calculate', 'authorize']];
        yield 'class mask' => [['classes' => ['App\\Http\\*Controller'], 'visibilities' => ['protected']], ['authorize', 'inherited']];
        yield 'PHP class names ignore case and leading separator' => [['classes' => ['\\app\\http\\ordercontroller'], 'visibilities' => ['private']], ['calculate']];
        yield 'short names do not select namespaced classes' => [['classes' => ['OrderController']], []];
        yield 'unrelated class' => [['classes' => ['App\\Http\\OtherController']], []];
        yield 'paths and classes form a union without duplicate findings' => [['paths' => ['*Controller.php'], 'classes' => ['App\\Http\\OrderController'], 'visibilities' => ['protected']], ['authorize', 'inherited']];
        yield 'public includes implicit visibility' => [['visibilities' => ['public']], ['index', 'implicit']];
        yield 'empty visibility selection disables findings' => [['visibilities' => []], []];
    }

    public function testDoesNotExpandInheritedOrTraitMethods(): void
    {
        $findings = $this->findings(new ForbiddenMethodVisibility(classes: ['App\\Http\\OrderController']));
        self::assertSame(['App\\Http\\OrderController', 'App\\Http\\OrderController'], array_column($findings, 'class'));
        self::assertSame([6, 7], array_column($findings, 'line'));
    }

    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidConfiguration(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ForbiddenMethodVisibility(...$options);
    }

    public static function invalidOptions(): iterable
    {
        yield 'empty path' => [['paths' => ['']]];
        yield 'blank path' => [['paths' => ['  ']]];
        yield 'non-string path' => [['paths' => [42]]];
        yield 'empty class' => [['classes' => ['']]];
        yield 'blank class' => [['classes' => ['  ']]];
        yield 'non-string class' => [['classes' => [42]]];
        yield 'unknown visibility' => [['visibilities' => ['internal']]];
    }

    public function testCatalogKeepsThePolicyOptional(): void
    {
        $entry = RuleCatalog::definitions()['forbidden_method_visibility'];
        self::assertSame(ForbiddenMethodVisibility::class, $entry['class']);
        self::assertSame('forbidden_method_visibility', (new $entry['class'](...$entry['arguments']))->id());
        self::assertArrayNotHasKey('forbidden_method_visibility', RuleCatalog::standardDefinitions());
        self::assertSame([], (new ForbiddenMethodVisibility)->requiredAnalyses());
    }

    public function testRunnerHonorsDirectoryTargetsAndFileExclusions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-visibility-');

        try {
            file_put_contents($path, '<?php abstract class Endpoint { private function __construct() {} abstract protected function authorize(); }');
            $rule = new ForbiddenMethodVisibility(paths: [dirname($path)]);
            $runner = new Runner;
            $result = $runner->check([$path], [$rule]);
            self::assertSame(['__construct', 'authorize'], $result->violations->pluck('method')->all());
            self::assertCount(2, $runner->check([$path], [new ForbiddenMethodVisibility(classes: ['Endpoint'])])->violations);
            $root = DIRECTORY_SEPARATOR === '\\' ? substr($path, 0, 3) : '/';
            self::assertCount(2, $runner->check([$path], [new ForbiddenMethodVisibility(paths: [$root])])->violations);
            $rule->exclude([$path]);
            self::assertTrue($runner->check([$path], [$rule])->violations->isEmpty());
        } finally {
            unlink($path);
        }
    }

    public function testPathAndClassSelectionsDoNotLeakIntoEachOther(): void
    {
        $directory = sys_get_temp_dir().'/soda-visibility-'.uniqid();
        $cwd = getcwd();
        mkdir($directory.'/App/Actions', 0700, true);
        file_put_contents($directory.'/App/Actions/Store.php', '<?php namespace Legacy; class Store { private function calculate() {} }');
        file_put_contents($directory.'/Elsewhere.php', '<?php namespace App\Actions; class Store { private function calculate() {} }');

        try {
            chdir($directory);
            $files = ['App/Actions/Store.php', 'Elsewhere.php'];
            $runner = new Runner;
            $byClass = $runner->check($files, [new ForbiddenMethodVisibility(classes: ['App\\Actions\\*'])]);
            $byPath = $runner->check($files, [new ForbiddenMethodVisibility(paths: ['App\\Actions\\*'])]);

            self::assertSame(['App\\Actions\\Store'], $byClass->violations->pluck('class')->all());
            self::assertSame(['Legacy\\Store'], $byPath->violations->pluck('class')->all());
        } finally {
            chdir($cwd);
            unlink($directory.'/Elsewhere.php');
            unlink($directory.'/App/Actions/Store.php');
            rmdir($directory.'/App/Actions');
            rmdir($directory.'/App');
            rmdir($directory);
        }
    }

    private function findings(ForbiddenMethodVisibility $rule): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-visibility-');

        try {
            file_put_contents($path, <<<'SOURCE'
<?php
namespace App\Http;
class OrderController extends BaseController {
    use SharedBehavior;
    public function index() { return new class { private function nested() {} }; }
    private static function calculate() {}
    protected function authorize() {}
    function implicit() {}
}
class BaseController { protected function inherited() {} }
trait SharedBehavior { private function shared() {} }
class Other { private function hidden() {} }
SOURCE);
            $file = (new FactCollector)->collect($path, $rule->requiredAnalyses());
            // The source path is independent of the temporary fixture location.
            $file = new FileFacts('app/Http/Controllers/OrderController.php', $file->source, $file->nodes, $file->metrics);

            return iterator_to_array($rule->checkFile($file));
        } finally {
            unlink($path);
        }
    }
}
