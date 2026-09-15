<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Commands\QualityCommand;
use Cosmira\Soda\Config\ConfigLoader;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Complexity\NoRepeatedCompoundConditions;
use Cosmira\Soda\Rules\Structure\NoTrivialFactories;
use Illuminate\Console\OutputStyle;
use Illuminate\Events\Dispatcher;
use InvalidArgumentException;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require_once __DIR__.'/../../docs/research/explicit-behavior/NoTrivialFactories.php';
require_once __DIR__.'/../../docs/research/explicit-behavior/NoRepeatedCompoundConditions.php';

final class ExplicitBehaviorCandidatesTest extends TestCase
{
    private function findings(Check $rule, string $source): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse('<?php '.$source) ?? [];
        $nodes = (new NodeTraverser(new NameResolver))->traverse($nodes);

        $facts = new FileFacts('/project/example.php', $source, $nodes, []);
        $findings = iterator_to_array($rule->checkFile($facts));
        if ($rule instanceof NoTrivialFactories) {
            $frozen = new Research\ExplicitBehavior\NoTrivialFactories;
            self::assertEquals(iterator_to_array($frozen->checkFile($facts)), $findings);
        }

        return $findings;
    }

    private function factory(string $method = 'public function create(int $amount, string $currency): Receipt { return new Receipt($amount, $currency); }'): string
    {
        return '/** @internal */ final class Factory { '.$method.' }';
    }

    public function testFactoryEvidenceAndIndependenceFromClassName(): void
    {
        $findings = $this->findings(new NoTrivialFactories, 'namespace Example; '.$this->factory());
        self::assertCount(1, $findings);
        self::assertSame([
            'rule'    => 'no_trivial_factories', 'file' => '/project/example.php', 'method' => 'create',
            'class'   => 'Example\\Factory', 'line' => 1, 'value' => 1, 'threshold' => 0,
            'message' => 'Example\\Factory::create() only constructs Example\\Receipt and forwards all parameters unchanged. Consider constructing Example\\Receipt at the call site.',
        ], $findings[0]->toArray());
        self::assertSame([], (new NoTrivialFactories)->requiredAnalyses());
        self::assertCount(1, $this->findings(new NoTrivialFactories, $this->factory('public function create() { return new Receipt(); }')));
    }

    #[DataProvider('factoryExceptions')]
    public function testFactoryBoundaries(string $source): void
    {
        self::assertSame([], $this->findings(new NoTrivialFactories, $source));
    }

    public static function factoryExceptions(): iterable
    {
        $method = 'public function create($a) { return new Receipt($a); }';
        foreach (['class F', 'final class F extends Base', 'final class F implements Contract', '#[Boundary] final class F'] as $declaration) {
            yield $declaration => ['/** @internal */ '.$declaration.' { '.$method.' }'];
        }
        yield 'public API' => ['final class F { '.$method.' }'];
        yield 'not a docblock' => ['/* @internal */ final class F { '.$method.' }'];
        yield 'different annotation' => ['/** @internalish */ final class F { '.$method.' }'];
        foreach (['private $state;', 'const KEY = 1;', 'use Behavior;', 'public function other() {}', 'public function __construct() {}'] as $member) {
            yield $member => ['/** @internal */ final class F { '.$member.$method.' }'];
        }
        foreach ([
            'transform'           => 'public function create($a) { return new Receipt(trim($a)); }',
            'named argument'      => 'public function create($a) { return new Receipt(amount: $a); }',
            'unpacking'           => 'public function create($a) { return new Receipt(...$a); }',
            'variadic'            => 'public function create(...$a) { return new Receipt($a); }',
            'default'             => 'public function create($a = 1) { return new Receipt($a); }',
            'reference'           => 'public function create(&$a) { return new Receipt($a); }',
            'reference return'    => 'public function &create($a) { return new Receipt($a); }',
            'dynamic class'       => 'public function create($a) { return new $a($a); }',
            'self'                => 'public function create($a) { return new self($a); }',
            'static new'          => 'public function create($a) { return new static($a); }',
            'anonymous'           => 'public function create($a) { return new class($a) {}; }',
            'named construction'  => 'public function euros($a) { return new Receipt($a); }',
            'static method'       => 'public static function create($a) { return new Receipt($a); }',
            'private method'      => 'private function create($a) { return new Receipt($a); }',
            'protected method'    => 'protected function create($a) { return new Receipt($a); }',
            'extra statement'     => 'public function create($a) { authorize(); return new Receipt($a); }',
            'reorder'             => 'public function create($a, $b) { return new Receipt($b, $a); }',
            'duplicate'           => 'public function create($a, $b) { return new Receipt($a, $a); }',
            'unused'              => 'public function create($a, $b) { return new Receipt($a); }',
            'method attribute'    => '#[Boundary] public function create($a) { return new Receipt($a); }',
            'parameter attribute' => 'public function create(#[Boundary] $a) { return new Receipt($a); }',
        ] as $name => $body) {
            yield $name => ['/** @internal */ final class F { '.$body.' }'];
        }
    }

    private function repeated(string $condition = '$this->active && ! $this->suspended', int $count = 3, string $declaration = 'class Subscription', string $properties = 'private bool $active; private bool $suspended;'): string
    {
        $methods = '';
        for ($index = 0; $index < $count; $index++) {
            $methods .= "\npublic function action$index() { if ($condition) {} }";
        }

        return $declaration.' { '.$properties.$methods.' }';
    }

    public function testRepetitionThresholdsAndEvidence(): void
    {
        $rule = new NoRepeatedCompoundConditions;
        $findings = $this->findings($rule, $this->repeated());
        self::assertCount(1, $findings);
        self::assertSame(3, $findings[0]->value);
        self::assertSame(2, $findings[0]->threshold);
        self::assertSame(2, $findings[0]->line);
        self::assertSame('Subscription', $findings[0]->class);
        self::assertSame('no_repeated_compound_conditions', $findings[0]->rule);
        self::assertStringContainsString('action0() at lines 2; action1() at lines 3; action2() at lines 4', $findings[0]->message);
        self::assertSame([], $rule->requiredAnalyses());
        self::assertSame([], $this->findings($rule, $this->repeated(count: 2)));
        self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions(2), $this->repeated(count: 2)));
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions(4), $this->repeated()));
    }

    public function testRejectsInvalidThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NoRepeatedCompoundConditions(1);
    }

    #[DataProvider('unsupportedConditions')]
    public function testConditionVocabulary(string $condition): void
    {
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $this->repeated($condition)));
    }

    public static function unsupportedConditions(): iterable
    {
        foreach ([
            '$this->active', '$this->active && $local', '$this->active && $this->unknown',
            '$this->active && check()', '$this->active && $this->check()',
            '$this->active && $this->child->active', '$this->active && $this->values[0]',
            '$this->active && ($this->suspended = false)', '$this->active == true && $this->suspended',
            '$this->active and $this->suspended', '$this->active && SOME_CONSTANT',
            '$this->active && $this->{"suspended"}', '$this->active && ++$this->suspended',
        ] as $condition) {
            yield $condition => [$condition];
        }
    }

    public function testClassBoundariesAndHooks(): void
    {
        foreach (['class Subscription extends ParentType', 'class Subscription'] as $declaration) {
            $properties = $declaration === 'class Subscription' ? 'use Concern; private $active; private $suspended;' : 'private $active; private $suspended;';
            self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $this->repeated(declaration: $declaration, properties: $properties)));
        }
        foreach ([
            'public function __get($name) {}', 'public function __set($name, $value) {}',
            'public function __isset($name) {}', 'public function __unset($name) {}',
            'public bool $hooked { get => true; }',
            'public function __construct(public bool $hooked { get => true; }) {}',
        ] as $member) {
            self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $this->repeated(properties: 'private $active; private $suspended; '.$member)));
        }
        self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions, $this->repeated(properties: 'public function __construct(private bool $active, private bool $suspended) {}')));
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $this->repeated(properties: 'private static $active; private $suspended;')));
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $this->repeated(count: 2).$this->repeated(count: 2, declaration: 'class Other')));
    }

    public function testStructuralIdentityAndMultipleGroups(): void
    {
        $source = $this->repeated();
        $source = str_replace('if ($this->active && ! $this->suspended)', 'if (($this->active) /* comment */ && !($this->suspended))', $source);
        self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions, $source));
        foreach (['$this->suspended && ! $this->active', '$this->active || ! $this->suspended'] as $other) {
            $source = str_replace('action2() { if ($this->active && ! $this->suspended)', 'action2() { if ('.$other.')', $this->repeated());
            self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $source));
        }
        $condition = '$this->active === true && $this->suspended !== null';
        self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions, $this->repeated($condition)));
        $source = str_replace('action2() { if ('.$condition.')', 'action2() { if ($this->active === false && $this->suspended !== null)', $this->repeated($condition));
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $source));
        $source = str_replace(') {} }', ') {} if ($this->active || $this->suspended) {} }', $this->repeated());
        self::assertCount(2, $this->findings(new NoRepeatedCompoundConditions, $source));
    }

    public function testControlFormsAndNestedScopeIsolation(): void
    {
        $condition = '$this->active && ! $this->suspended';
        foreach ([
            'if (false) {} elseif (COND) {}', 'while (COND) {}', 'do {} while (COND);',
            'for (; COND;) {}', '$result = COND ? 1 : 0;',
        ] as $control) {
            $source = str_replace('if ('.$condition.') {}', str_replace('COND', $condition, $control), $this->repeated());
            self::assertCount(1, $this->findings(new NoRepeatedCompoundConditions, $source), $control);
        }
        foreach ([
            '$f = function () { if (COND) {} };', '$f = fn () => COND ? 1 : 0;',
            'function nested() { if (COND) {} }',
            '$f = new class { private $active; private $suspended; public function run() { if (COND) {} } };',
            'for (; COND, true;) {}',
        ] as $control) {
            $source = str_replace('if ('.$condition.') {}', str_replace('COND', $condition, $control), $this->repeated());
            self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $source), $control);
        }
        $source = $this->repeated(count: 1);
        $source = str_replace('if ('.$condition.') {}', str_repeat('if ('.$condition.') {}', 4), $source);
        self::assertSame([], $this->findings(new NoRepeatedCompoundConditions, $source));
    }

    public function testRealCommandConfigurationAndJson(): void
    {
        $directory = sys_get_temp_dir().'/soda-explicit-'.uniqid();
        mkdir($directory, 0700);
        mkdir($directory.'/src', 0700);
        $source = $directory.'/src/Example.php';
        $config = $directory.'/soda.php';
        $report = $directory.'/report.json';
        file_put_contents($source, '<?php '.$this->factory().$this->repeated());
        $container = new Application;
        $console = new \Illuminate\Console\Application($container, new Dispatcher($container), 'test');
        $console->setAutoExit(false);
        $console->add(new QualityCommand);

        try {
            foreach ([true, false] as $enabled) {
                $rules = $enabled ? 'new \\'.NoTrivialFactories::class.'(), new \\'.NoRepeatedCompoundConditions::class.'()' : '';
                file_put_contents($config, '<?php return \\Cosmira\\Soda\\Config\\Soda::configure()->with(['.$rules.']);');
                $input = new ArrayInput([
                    'command' => 'quality', 'path' => [$directory.'/src'], '--config' => $config, '--report-json' => $report,
                ]);
                $output = new BufferedOutput;
                self::assertSame($enabled ? 1 : 0, $console->run($input, new OutputStyle($input, $output)));
                self::assertFileExists($report, $output->fetch());
                $json = json_decode(file_get_contents($report), true, flags: JSON_THROW_ON_ERROR);
                self::assertSame(4, $json['schema_version']);
                self::assertSame(! $enabled, $json['passed']);
                self::assertCount($enabled ? 2 : 0, $json['violations']);
                if ($enabled) {
                    self::assertEqualsCanonicalizing(['no_trivial_factories', 'no_repeated_compound_conditions'], array_column($json['violations'], 'rule'));
                    foreach ($json['violations'] as $violation) {
                        self::assertSame(realpath($source), $violation['file']);
                        self::assertNotEmpty($violation['message']);
                        self::assertGreaterThan(0, $violation['line']);
                    }
                }
            }
        } finally {
            foreach ([$source, $config, $report] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            rmdir($directory.'/src');
            rmdir($directory);
        }
    }

    public function testPredicateExtractionPreservesOrderAndReevaluatesState(): void
    {
        $before = new class
        {
            public bool $active = true;
            public bool $suspended = false;
            public array $events = [];

            public function renew(): void
            {
                if ($this->active && ! $this->suspended) {
                    $this->events[] = 'renew';
                    $this->suspended = true;
                }
            }

            public function notice(): void
            {
                if ($this->active && ! $this->suspended) {
                    $this->events[] = 'notice';
                }
            }

            public function offer(): void
            {
                if ($this->active && ! $this->suspended) {
                    $this->events[] = 'offer';
                }
            }
        };
        $after = new class
        {
            public bool $active = true;
            public bool $suspended = false;
            public array $events = [];

            private function canRenew(): bool
            {
                return $this->active && ! $this->suspended;
            }

            public function renew(): void
            {
                if ($this->canRenew()) {
                    $this->events[] = 'renew';
                    $this->suspended = true;
                }
            }

            public function notice(): void
            {
                if ($this->canRenew()) {
                    $this->events[] = 'notice';
                }
            }

            public function offer(): void
            {
                if ($this->canRenew()) {
                    $this->events[] = 'offer';
                }
            }
        };
        foreach ([$before, $after] as $subscription) {
            $subscription->notice();
            $subscription->renew();
            $subscription->offer();
            $subscription->suspended = false;
            $subscription->offer();
        }
        self::assertSame(['notice', 'renew', 'offer'], $before->events);
        self::assertSame($before->events, $after->events);
    }

    public function testDirectConstructionPreservesValuesAndConstructorEffects(): void
    {
        $factory = new class
        {
            public function create(int $amount, string $currency): ExplicitBehaviorReceipt
            {
                return new ExplicitBehaviorReceipt($amount, $currency);
            }
        };
        ExplicitBehaviorReceipt::$events = [];
        $before = $factory->create(1000, 'EUR');
        $beforeEvents = ExplicitBehaviorReceipt::$events;
        ExplicitBehaviorReceipt::$events = [];
        $after = new ExplicitBehaviorReceipt(1000, 'EUR');
        self::assertEquals($before, $after);
        self::assertSame($beforeEvents, ExplicitBehaviorReceipt::$events);
        ExplicitBehaviorReceipt::$events = [];
    }

    public function testProductionRepetitionMatchesFrozenDetector(): void
    {
        $sources = [
            $this->repeated(), $this->repeated(count: 2),
            $this->repeated('$this->active === true && $this->suspended !== null'),
            $this->repeated('$this->active === -1 && $this->suspended !== 1.5'),
            $this->repeated('$this->active && check()'),
            $this->repeated(properties: 'public function __construct(private $active, private $suspended) {}'),
            $this->repeated(properties: 'private $active; private $suspended; public function __get($name) {}'),
        ];
        foreach ($sources as $source) {
            foreach ([2, 3, 4] as $minimum) {
                $actual = $this->findings(new NoRepeatedCompoundConditions($minimum), $source);
                $frozen = new Research\ExplicitBehavior\NoRepeatedCompoundConditions($minimum);
                self::assertEquals($this->findings($frozen, $source), $actual);
            }
        }
    }

    public function testDefaultConfigurationRunsBothRules(): void
    {
        $source = $this->factory().$this->repeated();
        $rules = (new ConfigLoader)->loadDefault()->checks();
        foreach ($rules as $rule) {
            if (in_array($rule->id(), ['no_trivial_factories', 'no_repeated_compound_conditions'], true)) {
                self::assertCount(1, $this->findings($rule, $source));
            }
        }
    }

    public function testRulesAreEnabledByDefault(): void
    {
        self::assertArrayHasKey('no_trivial_factories', RuleCatalog::standardDefinitions());
        self::assertArrayHasKey('no_repeated_compound_conditions', RuleCatalog::standardDefinitions());
        $rules = RuleCatalog::standard();
        $ids = array_map(fn (Check $rule): string => $rule->id(), $rules);
        self::assertContains('no_trivial_factories', $ids);
        self::assertContains('no_repeated_compound_conditions', $ids);
    }
}

final class ExplicitBehaviorReceipt
{
    public static array $events = [];

    public function __construct(public int $amount, public string $currency)
    {
        self::$events[] = [$amount, $currency];
    }
}
