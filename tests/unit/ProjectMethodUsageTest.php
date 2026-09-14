<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\AstFacts;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\LogicalLineMap;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectMethodUsageTest extends TestCase
{
    #[DataProvider('projects')]
    public function testScopedCallsAcrossFiles(array $sources, array $expected): void
    {
        $selective = $this->project($sources, ['methodUsage']);
        $full = $this->project($sources, null);
        $rule = new NoUnusedMethods;
        $actual = array_map(static fn ($hit) => $hit->class.'::'.$hit->method, [...$rule->checkProject($selective)]);
        sort($actual);
        sort($expected);
        self::assertSame($expected, $actual);
        self::assertEquals([...$rule->checkProject($full)], [...$rule->checkProject($selective)]);
        self::assertSame($actual, array_map(static fn ($hit) => $hit->class.'::'.$hit->method, $this->sorted($rule, $selective)));
    }

    private function sorted(NoUnusedMethods $rule, ProjectFacts $project): array
    {
        $hits = [...$rule->checkProject($project)];
        usort($hits, static fn ($left, $right) => ($left->class.'::'.$left->method) <=> ($right->class.'::'.$right->method));

        return $hits;
    }

    public static function projects(): iterable
    {
        yield 'owner calls separate concern' => [
            ['namespace App; trait Concern { private function save() {} }', 'namespace App; class Owner { use Concern; public function run() { $this->SAVE(); } }'], [],
        ];
        yield 'concern calls owner' => [
            ['namespace App; trait Concern { public function run() { $this->save(); } }', 'namespace App; class Owner { use Concern; private function save() {} }'], [],
        ];
        yield 'nested concern' => [
            ['namespace App; trait Inner { private function save() {} }', 'namespace App; trait Outer { use Inner; public function run() { $this->save(); } }', 'namespace App; class Owner { use Outer; }'], [],
        ];
        yield 'one violation at trait origin' => [
            ['trait Concern { private function save() {} }', 'class First { use Concern; }', 'class Second { use Concern; }'], ['Concern::save'],
        ];
        yield 'unused unconsumed trait excluded' => [['trait Concern { private function save() {} }'], []];
        yield 'one consumer uses shared concern' => [
            ['trait Concern { private function save() {} }', 'class First { use Concern; }', 'class Second { use Concern; public function run() { $this->save(); } }'], [],
        ];
        yield 'foreign method does not use local' => [['class Owner { private function save() {} public function run($other) { $other->save(); } }'], ['Owner::save']];
        yield 'foreign dynamic stays local' => [['class Owner { private function save() {} public function run($other, $method) { $other->$method(); } }'], ['Owner::save']];
        yield 'dynamic owner uncertainty isolated' => [
            ['class Owner { private function save() {} public function run($method) { $this->$method(); } }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'dynamic trait consumer affects origin only' => [
            ['trait Concern { private function save() {} }', 'class Owner { use Concern; public function run($method) { $this->$method(); } }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'qualified trait import' => [
            ['namespace One; trait Concern { private function save() {} }', 'namespace Two; trait Concern { private function save() {} }', 'namespace App; use One\Concern as First; class Owner { use First; public function run() { $this->save(); } }', 'namespace Two; class Owner { use Concern; }'], ['Two\Concern::save'],
        ];
        yield 'inherited protected invocation' => [
            ['namespace App; class Base { protected function save() {} }', 'namespace App; class Middle extends Base {}', 'namespace App; class Child extends Middle { public function run() { $this->SAVE(); } }'], [],
        ];
        yield 'private names have lexical identity' => [
            ['class Base { private function save() {} public function run() { $this->save(); } }', 'class Child extends Base { private function save() {} }'], ['Child::save'],
        ];
        yield 'child cannot call parent private' => [
            ['class Base { private function save() {} }', 'class Child extends Base { public function run() { $this->save(); } }'], ['Base::save'],
        ];
        yield 'parent bypasses override' => [
            ['class Base { protected static function save() {} }', 'class Child extends Base { protected static function save() {} public function run() { parent::save(); } }'], ['Child::save'],
        ];
        yield 'self stays lexical' => [
            ['class Base { protected static function save() {} public function run() { self::save(); } }', 'class Child extends Base { protected static function save() {} }'], ['Child::save'],
        ];
        yield 'static considers configured descendants' => [
            ['class Base { protected static function save() {} public function run() { static::save(); } }', 'class Child extends Base { protected static function save() {} }'], [],
        ];
        yield 'abstract ancestor contract cross file' => [
            ['namespace App; abstract class Base { abstract protected function save(); }', 'namespace App; class Child extends Base { protected function SAVE() {} }'], [],
        ];
        yield 'nested interface contract' => [
            ['interface Root { public function save(); }', 'interface Contract extends Root {}', 'class Owner implements Contract { protected function save() {} }'], [],
        ];
        yield 'alias uses source declaration' => [
            ['trait Concern { private function save() {} }', 'class Owner { use Concern { save as store; } public function run() { $this->store(); } }'], [],
        ];
        yield 'public alias is exposed' => [
            ['trait Concern { private function save() {} }', 'class Owner { use Concern { save as public store; } }'], [],
        ];
        yield 'conflict selection and excluded alias' => [
            ['trait First { private function save() {} }', 'trait Second { private function save() {} }', 'class Owner { use First, Second { First::save insteadof Second; Second::save as backup; } public function run() { $this->save(); $this->backup(); } }'], [],
        ];
        yield 'unresolved trait isolated' => [
            ['class Owner { use Missing; private function save() {} }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'cycle terminates conservatively' => [
            ['trait First { use Second; private function save() {} }', 'trait Second { use First; }', 'class Owner { use First; private function work() {} }'], [],
        ];
        yield 'closure retains this and static closure does not' => [
            ['class Owner { private function save() {} public function run() { return fn () => $this->save(); } }'], [],
        ];
        yield 'nested class cannot use owner method' => [
            ['class Owner { private function save() {} public function run() { return new class { public function run() { $this->save(); } }; } }'], ['Owner::save'],
        ];
        yield 'named nested function has no this' => [
            ['class Owner { private function save() {} public function run() { function nested() { $this->save(); } } }'], ['Owner::save'],
        ];
        yield 'class implementation resolves trait conflict' => [
            ['trait First { private function save() {} }', 'trait Second { private function save() {} }', 'class Owner { use First, Second; private function save() {} }'], ['Owner::save'],
        ];
        yield 'trait visibility adjustment does not expose private alias' => [
            ['trait Concern { private function save() {} }', 'class Owner { use Concern { save as protected; } }', 'class Child extends Owner { public function run() { $this->save(); } }'], [],
        ];
        yield 'unresolved alias isolated' => [
            ['trait Concern { private function save() {} }', 'class Owner { use Concern { missing as alias; } private function work() {} }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'case insensitive external override and magic names' => [
            ['class Owner { #[\Override] protected function save() {} private function __TOSTRING() {} }'], [],
        ];
        yield 'concrete ancestor is not a contract' => [
            ['class Base { protected function save() {} }', 'class Owner extends Base { protected function save() {} }'], ['Base::save', 'Owner::save'],
        ];
        yield 'unrelated interface does not exempt method' => [
            ['interface Contract { public function run(); }', 'class Owner implements Contract { protected function save() {} }'], ['Owner::save'],
        ];
        yield 'class cycle stays conservative' => [
            ['class Owner extends Other { private function save() {} }', 'class Other extends Owner {}'], [],
        ];
        yield 'self inheritance terminates' => [['class Owner extends Owner { private function save() {} }'], []];
        yield 'unresolved parent remains local' => [
            ['class Owner extends Missing { protected function save() {} }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'duplicate types stay conservative' => [
            ['class Owner { private function save() {} }', 'class Owner { private function work() {} }'], [],
        ];
        yield 'static closure cannot call this' => [['class Owner { private function save() {} public function run() { return static fn () => $this->save(); } }'], ['Owner::save']];
        yield 'static closure preserves self' => [['class Owner { private static function save() {} public function run() { return static fn () => self::save(); } }'], []];
        yield 'new known receiver in own scope' => [['class Owner { private function save() {} public function run() { (new Owner)->save(); } }'], []];
        yield 'fluent receiver keeps uncertainty local' => [
            ['class Owner { private function save() {} public function run() { $this->fluent()->save(); } }', 'class Other { private function save() {} }'], ['Other::save'],
        ];
        yield 'alias of this is uncertain' => [['class Owner { private function save() {} public function run() { $alias = $this; $alias->save(); } }'], []];
        yield 'typed closure receiver preserves private call' => [['class Owner { private function save() {} public function run() { return static fn (self $item) => $item->save(); } }'], []];
        yield 'typed method receiver preserves private call' => [['class Owner { private function save() {} public function run(self $other) { $other->save(); } }'], []];
        yield 'foreign typed receiver stays foreign' => [['class Owner { private function save() {} public function run(Foreign $other) { $other->save(); } }'], ['Owner::save']];
        yield 'untyped closure parameter shadows typed receiver' => [['class Owner { private function save() {} public function run(self $other) { return function ($other) { $other->save(); }; } }'], ['Owner::save']];
        yield 'class literal factory retains local uncertainty' => [['class Owner { private function save() {} public static function run($factory) { $result = $factory->create(self::class); $result->save(); } }', 'class Foreign { private function save() {} }'], ['Foreign::save']];
        yield 'fresh instance alias retains local uncertainty' => [['class Owner { private function save() {} public function run() { $result = new self; $result->save(); } }'], []];
        yield 'foreign callback does not use local method' => [['class Owner { private function save() {} public function run($other) { return [$other, "save"]; } }'], ['Owner::save']];
        yield 'dynamic foreign callback does not suppress owner' => [['class Owner { private function save() {} public function run($other, $method) { return [$other, $method]; } }'], ['Owner::save']];
        yield 'enum retains candidate categories' => [['enum Status { case Pending; private function save() {} }'], []];
        yield 'anonymous class has own method scope' => [['class Owner { public function run() { return new class { private function save() {} public function run() { $this->save(); } }; } }'], []];
        foreach (['$this->save(...)', '$this?->save()', 'self::save(...)', '[$this, "SAVE"]', '[self::class, "save"]', 'call_user_func_array([$this, "save"], [])', '[$this, $method]', 'call_user_func("Owner::save")', 'static::$method()'] as $call) {
            yield $call => [['class Owner { private function save() {} public function run() { return '.$call.'; } }'], []];
        }
    }

    public function testLocationsAndIgnoreConfigurationSurviveProjectTransition(): void
    {
        $project = $this->project(['namespace Domain; trait Concern { private function Save() {} }', 'namespace Domain; class Owner { use Concern; private function setUP() {} }'], ['methodUsage']);
        [$hit] = [...(new NoUnusedMethods)->checkProject($project)];
        self::assertSame('source0.php', $hit->file);
        self::assertSame(1, $hit->line);
        self::assertSame('Domain\\Concern', $hit->class);
        self::assertSame('Save', $hit->method);
        self::assertSame([], [...(new NoUnusedMethods(ignore: ['SAVE']))->checkProject($project)]);
    }

    public function testProjectFactsReleaseAstAndContainOnlyScalarsAndArrays(): void
    {
        $source = '<?php class Owner { private function save() {} }';
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $reference = \WeakReference::create($nodes[0]);
        $metrics = AstFacts::collect($nodes, LogicalLineMap::fromSource($source), ['methodUsage']);
        $file = new FileFacts('owner.php', $source, $nodes, $metrics);
        $project = new ProjectFacts;
        $project->add($file);
        unset($nodes, $file, $metrics);
        gc_collect_cycles();
        self::assertNull($reference->get());
        array_walk_recursive($project->files, static function ($value): void {
            self::assertFalse(is_object($value));
        });
        self::assertCount(1, [...(new NoUnusedMethods)->checkProject($project)]);
    }

    private function project(array $sources, ?array $required): ProjectFacts
    {
        $project = new ProjectFacts;
        foreach ($sources as $index => $source) {
            $source = '<?php '.$source;
            $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
            $metrics = AstFacts::collect($nodes, LogicalLineMap::fromSource($source), $required);
            $file = new FileFacts('source'.$index.'.php', $source, $nodes, $metrics);
            $project->add($file);
        }

        return $project;
    }
}
