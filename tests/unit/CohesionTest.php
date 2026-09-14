<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Research\MaxDisconnectedMethodGroups;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/docs/research/MaxDisconnectedMethodGroups.php';

final class CohesionTest extends TestCase
{
    #[DataProvider('cases')]
    public function testResolvesGraphComponentsAndMarksUnknownRelationships(string $source, array $expected): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-cohesion-');
        file_put_contents($path, '<?php '.$source);

        try {
            $collector = new FactCollector;
            $project = new ProjectFacts;
            $project->add($collector->collect($path, ['cohesion']));
            $project->resolveClasses();
            $views = $project->files[$path]['cohesion'];
            foreach ($expected as $name => [$groups, $pairs, $possible, $unknown]) {
                $graph = $views[$name]['composed'];
                self::assertSame($groups, $graph['metrics']['method_groups'], $name);
                self::assertSame($pairs, $graph['metrics']['tcc_pairs'], $name);
                self::assertSame($possible, $graph['metrics']['tcc_possible_pairs'], $name);
                self::assertSame($unknown, $graph['unknown'] !== [], $name.': '.json_encode($graph['unknown']));
                self::assertEquals($possible === 0 ? null : $pairs / $possible, $graph['tcc']);
            }
            $full = new ProjectFacts;
            $full->add($collector->collect($path));
            $full->resolveClasses();
            self::assertSame($views, $full->files[$path]['cohesion']);
            $project->resolveClasses();
            self::assertSame($views, $project->files[$path]['cohesion']);
            foreach ((new MaxDisconnectedMethodGroups(1, 2))->checkProject($project) as $violation) {
                self::assertSame(0, $views[$violation->class]['composed']['metrics']['cohesion_unknown']);
                self::assertSame($path, $violation->file);
                self::assertGreaterThan(1, $violation->value);
                self::assertStringContainsString('not a count of responsibilities', $violation->message);
            }
        } finally {
            unlink($path);
        }
    }

    public function testCrossFileCompositionParsesEachFileOnceAndRetainsOnlyFacts(): void
    {
        $sources = [
            'namespace Domain; trait Values { function value() { return $this->state; } }',
            'namespace Domain; class Base { protected $state; function read() { return $this->state; } }',
            'namespace Domain; class Record extends Base { use Values; function text() { if ($this->state) { return "yes"; } return "no"; } }',
        ];
        $realParser = (new ParserFactory)->createForNewestSupportedVersion();
        $parser = $this->createMock(Parser::class);
        $parser->expects(self::exactly(3))->method('parse')->willReturnCallback($realParser->parse(...));
        $collector = new FactCollector($parser);
        $project = new ProjectFacts;
        $paths = [];
        $roots = [];

        try {
            foreach ($sources as $source) {
                $path = tempnam(sys_get_temp_dir(), 'soda-composition-');
                $paths[] = $path;
                file_put_contents($path, '<?php '.$source);
                $file = $collector->collect($path, ['cohesion', 'cognitive']);
                $roots[] = \WeakReference::create($file->nodes[0]);
                $project->add($file);
                unset($file);
            }
            $project->resolveClasses();
            $owner = $project->files[$paths[2]];
            self::assertSame(1, $owner['cohesion']['Domain\\Record']['composed']['metrics']['method_groups']);
            self::assertSame(3, $owner['cohesion']['Domain\\Record']['composed']['metrics']['tcc_pairs']);
            self::assertSame(0, $owner['cohesion']['Domain\\Record']['composed']['metrics']['cohesion_unknown']);
            self::assertSame(1, $owner['methods']['Domain\\Record::text']['cognitive_complexity']);
            array_walk_recursive($project->files, static fn ($value) => self::assertFalse(is_object($value), 'Project facts must not retain AST objects.'));
            unset($collector, $parser, $realParser);
            gc_collect_cycles();
            foreach ($roots as $root) {
                self::assertNull($root->get(), 'AST must be collectible after file checks.');
            }
        } finally {
            foreach ($paths as $path) {
                unlink($path);
            }
        }
    }

    public static function cases(): iterable
    {
        yield 'two components are an integer, not jPeek fractional formula' => ['class C { private $x; private $y; function a() { return $this->x; } function b() { return $this->x; } function c() { return $this->y; } }', ['C' => [2, 1, 3, false]]];
        yield 'method bridge joins independent fields' => ['class C { private $x; private $y; function a() { return $this->x; } function b() { return $this->y; } function bridge() { $this->a(); $this->b(); } }', ['C' => [1, 0, 3, false]]];
        yield 'private bridge affects components but not public TCC denominator' => ['class C { private $x; private $y; function a() { return $this->x; } function b() { return $this->y; } private function bridge() { $this->a(); $this->b(); } }', ['C' => [1, 0, 1, false]]];
        yield 'constructor does not connect everything it initializes' => ['class C { function __construct(private $x, private $y) {} function a() { return $this->x; } function b() { return $this->y; } }', ['C' => [2, 0, 1, false]]];
        yield 'string cast invokes the string protocol' => ['class C { private $x; function text() { return (string) $this; } function __toString() { return $this->x; } }', ['C' => [1, 0, 1, false]]];
        yield 'explicit string call has equivalent graph' => ['class C { private $x; function text() { return $this->__toString(); } function __toString() { return $this->x; } }', ['C' => [1, 0, 1, false]]];
        yield 'iteration of this remains unknown' => ['class C { function a() { foreach ($this as $value) {} } function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'cloning this remains unknown' => ['class C { function a() { return clone $this; } function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'stateless methods are disconnected without inventing fields' => ['class C { function a() { return 1; } function b() { return 2; } }', ['C' => [2, 0, 1, false]]];
        yield 'no methods has undefined TCC' => ['class C {}', ['C' => [0, 0, 0, false]]];
        yield 'one method has undefined TCC' => ['class C { function a() {} }', ['C' => [1, 0, 0, false]]];
        yield 'static and abstract methods excluded' => ['abstract class C { abstract function contract(); static function helper() {} function real() {} }', ['C' => [1, 0, 0, false]]];
        yield 'shared logger can connect unrelated work' => ['class C { private $logger; function invoice() { $this->logger->info("invoice"); } function ship() { $this->logger->info("ship"); } }', ['C' => [1, 1, 1, false]]];
        yield 'readonly getters need not become more objects' => ['readonly class C { function __construct(public int $x, public int $y) {} function x() { return $this->x; } function y() { return $this->y; } }', ['C' => [2, 0, 1, false]]];
        yield 'trait requirements resolved by owner' => ['trait T { function a() { return $this->x; } } class C { use T; private $x; function b() { return $this->x; } }', ['T' => [1, 0, 0, true], 'C' => [1, 1, 1, false]]];
        yield 'nested trait composition' => ['trait A { private $x; function a() { return $this->x; } } trait B { use A; function b() { return $this->x; } } class C { use B; }', ['C' => [1, 1, 1, false]]];
        yield 'PHP type and trait names are case insensitive' => ['trait Helpers { private $x; function a() { return $this->x; } } class C { use helpers; function b() { return $this->x; } }', ['C' => [1, 1, 1, false]]];
        yield 'cross-use conflicts remain unknown' => ['trait A { function a() {} } trait B { function a() {} } class C { use A; use B; }', ['C' => [1, 0, 0, true]]];
        yield 'property hooks remain unknown' => ['class C { public int $x { get => 1; } function a() { return $this->x; } function b() { return $this->x; } }', ['C' => [1, 1, 1, true]]];
        yield 'trait selection and excluded implementation alias' => ['trait A { private $x; function work() { return $this->x; } } trait B { private $y; function work() { return $this->y; } } class C { use A, B { A::work insteadof B; B::work as private other; } }', ['C' => [2, 0, 0, false]]];
        yield 'unresolved trait conflicts are not absent methods' => ['trait A { function work() {} } trait B { function work() {} } class C { use A, B; }', ['C' => [0, 0, 0, true]]];
        yield 'class method overrides conflicting traits' => ['trait A { function work() {} } trait B { function work() {} } class C { use A, B; function work() {} }', ['C' => [1, 0, 0, false]]];
        yield 'ordinary inherited protected state' => ['class B { protected $x; function a() { return $this->x; } } class C extends B { function b() { return $this->x; } }', ['C' => [1, 1, 1, false]]];
        yield 'inherited private scope deliberately unresolved' => ['class B { private $x; function a() { return $this->x; } } class C extends B { function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'unavailable dependency' => ['class C extends Missing { function work() {} }', ['C' => [1, 0, 0, true]]];
        yield 'unavailable trait' => ['class C { use Missing; function work() {} }', ['C' => [1, 0, 0, true]]];
        yield 'dynamic calls' => ['class C { function a($name) { $this->$name(); } function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'dynamic fields' => ['class C { function a($name) { return $this->$name; } function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'nested callable may use owner state' => ['class C { private $x; function a() { return fn () => $this->x; } function b() { return $this->x; } }', ['C' => [2, 0, 1, true]]];
        yield 'alias of this' => ['class C { function a() { $alias = $this; $alias->b(); } function b() {} }', ['C' => [2, 0, 1, true]]];
        yield 'cyclic declarations' => ['class A extends B {} class B extends A {}', ['A' => [0, 0, 0, true], 'B' => [0, 0, 0, true]]];
        yield 'enum methods have their own graph' => ['enum C { case Open; function a() { return $this->b(); } function b() { return 1; } }', ['C' => [1, 0, 1, false]]];
    }

    public function testAnonymousTypesAreSeparateAndLocalNamesDoNotChangeMeasurements(): void
    {
        $source = 'new class { private $x; function a() { $temporary = $this->x; return $temporary; } function b() { return $this->x; } };';
        $path = tempnam(sys_get_temp_dir(), 'soda-cohesion-');

        try {
            $graphs = [];
            foreach ([$source, str_replace(['$temporary', '; '], ['$renamed', ";\n"], $source)] as $code) {
                file_put_contents($path, '<?php '.$code);
                $project = new ProjectFacts;
                $project->add((new FactCollector)->collect($path, ['cohesion']));
                $project->resolveClasses();
                $rows = $project->files[$path]['cohesion'];
                self::assertCount(1, $rows);
                self::assertStringStartsWith('{anonymous}@', array_key_first($rows));
                $graphs[] = reset($rows)['composed']['metrics'];
                self::assertSame([], $project->files[$path]['classes']);
            }
            self::assertSame($graphs[0], $graphs[1]);
        } finally {
            unlink($path);
        }
    }
}
