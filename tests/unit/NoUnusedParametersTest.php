<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Rules\Usage\NoUnusedParameters;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoUnusedParametersTest extends TestCase
{
    #[DataProvider('cases')]
    public function testLexicalUsage(string $body, int $expected): void
    {
        $source = '<?php function operation($input) { '.$body.' }';
        $findings = $this->findings($source);
        self::assertCount($expected, $findings);
        if ($expected !== 0) {
            self::assertSame(1, $findings[0]->line);
            self::assertSame(1, $findings[0]->value);
            self::assertSame(0, $findings[0]->threshold);
            self::assertStringContainsString('$input', $findings[0]->message);
        }
    }

    public static function cases(): iterable
    {
        yield 'local variable snapshot' => ['return get_defined_vars();', 0];
        yield 'qualified snapshot' => ['return \\get_defined_vars();', 0];
        yield 'snapshot after overwrite' => ['$input = 1; return get_defined_vars();', 1];
        yield 'snapshot callable is not invoked' => ['return get_defined_vars(...);', 1];
        yield 'snapshot in arrow has its own scope' => ['return fn () => get_defined_vars();', 1];
        yield 'snapshot in closure has its own scope' => ['return function () { return get_defined_vars(); };', 1];
        yield 'unrelated qualified function' => ['return Other\\get_defined_vars();', 1];
        yield 'arrow return does not exit outer function' => ['$later = fn () => 1; return $input;', 0];
        yield 'arrow write does not overwrite outer input' => ['$later = fn () => $input = 1; return $input;', 0];
        yield 'unused'  => ['return 1;', 1];
        yield 'used' => ['return $input;', 0];
        yield 'passed' => ['consume($input);', 0];
        yield 'captured' => ['$later = function () use ($input) { return $input; };', 0];
        yield 'arrow capture' => ['$later = fn () => $input;', 0];
        yield 'shadowed arrow parameter' => ['$later = fn ($input) => $input;', 1];
        yield 'shadowed closure parameter' => ['$later = function ($input) { return $input; };', 1];
        yield 'nested named function' => ['function inner($input) { return $input; }', 1];
        yield 'anonymous object method' => ['$object = new class { function inner($input) { return $input; } };', 1];
    }

    public function testIncomingValueMustSurviveUntilARead(): void
    {
        foreach ([
            '$input = 1; return $input;',
            '$input;',
            'ready() ? ($input = 1) : ($input = 2); return $input;',
            'if (ready()) { $input = 1; } else { $input = 2; } return $input;',
            'if (ready()) { return 1; } $input = 2; return $input;',
        ] as $body) {
            self::assertCount(1, $this->findings('<?php function operation($input) { '.$body.' }'), $body);
        }
        foreach ([
            '$copy = $input; $input = 1; return $copy;',
            'if (ready()) { $input = 1; } return $input;',
            '$input = transform($input); return $input;',
            'ready() && ($input = 1); return $input;',
            'ready() || ($input = 1); return $input;',
            'ready() and ($input = 1); return $input;',
            'ready() or ($input = 1); return $input;',
            'ready() ?? ($input = 1); return $input;',
            'ready() ? ($input = 1) : null; return $input;',
            'ready() ? null : ($input = 1); return $input;',
        ] as $body) {
            self::assertSame([], $this->findings('<?php function operation($input) { '.$body.' }'), $body);
        }
        self::assertSame([], $this->findings('<?php function fill(&$output) { $output = 1; }'));
    }

    public function testPromotionAndAbstractContractsHaveNoUnusedInput(): void
    {
        self::assertSame([], $this->findings('<?php interface Contract { function run($input); } class Record { function __construct(public string $input) {} }'));
    }

    public function testKnownInterfacePreservesOnlyDeclaredPositions(): void
    {
        $source = '<?php namespace App; interface Contract { function run($input); } class Worker implements Contract { function run($input) {} }';
        self::assertSame([], $this->findings($source));
        $extra = str_replace('function run($input) {}', 'function run($input, $extra = null) {}', $source);
        $findings = $this->findings($extra);
        self::assertCount(1, $findings);
        self::assertStringContainsString('$extra', $findings[0]->message);
        self::assertSame([], $this->findings($extra, ['App\\Worker::run']));
    }

    public function testCallbackPositionsBeforeLastReadAreRequired(): void
    {
        foreach ([
            'function ($type, $buffer) { return $buffer; }',
            'fn ($type, $buffer) => $buffer',
            'function ($app, $name, $config) { return $config; }',
        ] as $callback) {
            self::assertSame([], $this->findings('<?php consume('.$callback.');'));
        }
        self::assertCount(1, $this->findings('<?php consume(fn ($first, $used, $last) => $used);'));
        self::assertCount(2, $this->findings('<?php consume(fn ($first, $last) => 1);'));
        self::assertCount(1, $this->findings('<?php function own($unused, $used) { return $used; }'));
        self::assertCount(2, $this->findings('<?php consume(fn ($unused, $overwritten) => $overwritten = 1);'));
    }

    public function testRegisteredNativeStreamHooksPreserveOnlyNativePositions(): void
    {
        $hooks = 'public function stream_open($path, $mode, $options, &$opened) {} public function stream_set_option($option, $one, $two) {} public function url_stat($path, $flags) {}';
        self::assertCount(9, $this->findings('<?php class Stream { '.$hooks.' }'));
        self::assertSame([], $this->findings('<?php class Stream { '.$hooks.' public static function register() { stream_wrapper_register("test", __CLASS__); } }'));
        self::assertCount(1, $this->findings('<?php class Stream { public function url_stat($path, $flags, $extra) {} public static function register() { stream_wrapper_register("test", __CLASS__); } }'));
        self::assertCount(2, $this->findings('<?php class Stream { public function url_stat($path, $flags) {} public function other() { return new class { public function register() { stream_wrapper_register("test", __CLASS__); } }; } }'));
    }

    private function findings(string $source, array $contracts = []): array
    {
        $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
        $nodes = (new NodeTraverser(new NameResolver, new ParentConnectingVisitor))->traverse($nodes);

        return iterator_to_array((new NoUnusedParameters($contracts))->checkFile(new FileFacts('/project/input.php', $source, $nodes, [])));
    }
}
