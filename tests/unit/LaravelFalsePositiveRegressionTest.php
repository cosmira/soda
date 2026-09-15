<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Minimal reproductions from the Laravel audits, exercised through the standard runner.
 * Each case also proves that the corresponding real violation remains detectable.
 */
final class LaravelFalsePositiveRegressionTest extends TestCase
{
    #[DataProvider('regressions')]
    public function testSelectedPipelinePreservesValidCodeAndDetectsControl(
        string $valid,
        string $invalid,
        array $rules,
    ): void {
        foreach ($rules as $rule) {
            self::assertArrayHasKey($rule, RuleCatalog::definitions());
        }

        $validFindings = $this->findings($valid, $rules);
        $invalidFindings = $this->findings($invalid, $rules);
        foreach ($rules as $rule) {
            self::assertNotContains($rule, $validFindings, 'False positive returned: '.$rule);
            self::assertContains($rule, $invalidFindings, 'Real violation stopped being detected: '.$rule);
        }
    }

    public static function regressions(): iterable
    {
        yield 'Prompts: else is an alternative, not another nesting level' => [
            'function map($callback, array $values) { try { if ($callback) {} else { foreach ($values as $value) { consume($value); } } } catch (Exception $error) {} }',
            'function map($callback, array $values) { try { if ($callback) {} else { foreach ($values as $value) { if ($value) { consume($value); } } } } catch (Exception $error) {} }',
            ['max_control_nesting'],
        ];
        yield 'Alternative catch body starts at the same depth as try' => [
            'function handle() { try {} catch (Exception $error) { foreach (items() as $item) { if ($item) { consume($item); } } } }',
            'function handle() { try {} catch (Exception $error) { foreach (items() as $item) { if ($item) { while (ready()) { consume($item); } } } } }',
            ['max_control_nesting'],
        ];
        yield 'Pail: a single query with null fallback is a simple condition' => [
            'function testing() { if (Env::get("TESTS") ?? false) { runTests(); } }',
            'function testing() { if (Env::get(configKey()) ?? false) { runTests(); } }',
            ['no_complex_control_conditions'],
        ];
        yield 'Prompts and Pail: a single query may be tested with instanceof' => [
            'function render() { if (output() instanceof BufferedOutput) { flushOutput(); } }',
            'function render() { if (output(resolveTarget()) instanceof BufferedOutput) { flushOutput(); } }',
            ['no_complex_control_conditions'],
        ];
        yield 'Prompts: callback body is outside the surrounding condition' => [
            'function currentLine(array $lines): int { $total = 0; return search($lines, function ($line) use (&$total) { $total += strlen($line); return $total > 10; }) ?: 0; }',
            'function currentLine(array $lines): int { return ($total = count($lines)) ? $total : 0; }',
            ['no_assignment_in_condition'],
        ];
        yield 'Prompts: immediately invoked closure has an explicit target' => [
            'function resetState() { return (function () { clearState(); return 1; })(); }',
            'function resetState($callback) { return call_user_func($callback); }',
            ['no_dynamic_invocation'],
        ];
        yield 'Strict policy: exhaustive branches still forbid else' => [
            'function label(bool $enabled): string { if ($enabled) { return "on"; } return "off"; }',
            'function label(bool $enabled): string { if ($enabled) { return "on"; } else { return "off"; } }',
            ['no_else_branches'],
        ];
        yield 'Strict policy: mutually exclusive cases still forbid elseif' => [
            'function label(int $status): string { if ($status === 1) { return "on"; } if ($status === 2) { return "off"; } return "unknown"; }',
            'function label(int $status): string { if ($status === 1) { return "on"; } elseif ($status === 2) { return "off"; } return "unknown"; }',
            ['no_else_branches'],
        ];
        yield 'Sandbox: ordinary list indices need no wrapper' => [
            'function first(array $keys) { return $keys[0]; }',
            '/** @param array{string, int} $row */ function label(array $row): string { return $row[0].$row[1]; }',
            ['no_numeric_array_index'],
        ];
        yield 'Sandbox: independent array copy survives unset' => [
            'function restore(array $attributes): array { $values = $attributes; unset($values["id"]); return $values; }',
            'function restore(array $attributes): array { $values = $attributes; return $values; }',
            ['useless_variable'],
        ];
        yield 'Prompts: parameters forwarded by variable snapshot' => [
            'function prompt(string $label, string $hint): array { return get_defined_vars(); }',
            'function prompt(string $label, string $hint): array { return []; }',
            ['no_unused_parameters'],
        ];
        yield 'Prompts: numeric data guard is not a boolean parameter' => [
            'function eraseLines(int $count): void { if ($count) { writeLines($count); } }',
            'function eraseLines(bool $enabled): void { if ($enabled) { writeLines(1); } }',
            ['no_boolean_parameters'],
        ];
        yield 'Scout: pagination defaults are data, not flags' => [
            'function paginate($page = null) { return $page ?: 1; }',
            'function paginate($simple = false) { return $simple ? simplePage() : fullPage(); }',
            ['no_boolean_parameters'],
        ];
        yield 'Prompts: validating mixed data does not declare a flag' => [
            'function invalid(mixed $value): bool { return $value === "" || $value === [] || $value === false || $value === null; }',
            'function invalid(bool|string $required): bool { return $required === false; }',
            ['no_boolean_parameters'],
        ];
        yield 'Sanctum: nullable model guard is not a flag' => [
            'function supportsTokens($tokenable = null) { return $tokenable && hasTokens($tokenable); }',
            'function supportsTokens($enabled = false) { return $enabled && hasTokens(currentToken()); }',
            ['no_boolean_parameters'],
        ];
        yield 'Scout: formatting a boolean value does not declare a flag' => [
            'function formatValue($value) { if (is_bool($value)) { return $value ? "true" : "false"; } return (string) $value; }',
            'function formatValue($quoted = false) { return $quoted ? quoteValue() : rawValue(); }',
            ['no_boolean_parameters'],
        ];
        yield 'Serializable Closure: optional secret is data' => [
            'function signer($secret) { return $secret ? new Hmac($secret) : null; }',
            'function signer(bool $signed) { return $signed ? new Hmac(secret()) : null; }',
            ['no_boolean_parameters'],
        ];
        yield 'Sanctum: third positional callback argument' => [
            'register(function ($app, $name, array $config) { return $config; });',
            'register(function ($app, $name, array $config, $unused) { return $config; });',
            ['no_unused_parameters'],
        ];
        yield 'Pail and Sail: second positional callback argument' => [
            'run(fn (string $type, string $buffer) => trim($buffer));',
            'run(fn (string $type, string $buffer, string $unused) => trim($buffer));',
            ['no_unused_parameters'],
        ];
        yield 'Pail and Sail: consumed ternary reads a value' => [
            'function name($user) { return $user->hasName() ? $user->name() : null; }',
            'function notify($user) { if ($user->isActive()) { $user->notify(); } }',
            ['max_ask_then_tell_patterns'],
        ];
        yield 'Pail: object owns its state decision' => [
            'class LogFile { function log() { if ($this->isStale()) { $this->destroy(); } } }',
            'class LogFile { function log() { if ($this->file->isStale()) { $this->file->destroy(); } } }',
            ['max_ask_then_tell_patterns'],
        ];
        yield 'Serializable Closure: inline example is documentation' => [
            "function reflect() {\n// Created from a method (e.g. `\$obj->method(...)`), attributes are inherited.\n}",
            "function reflect() {\n// \$obj->method();\n}",
            ['max_commented_out_code_lines'],
        ];
        yield 'Serializable Closure: PHPDoc property is a type consumer' => [
            'namespace App; interface Signer { function sign(); } class Signed { /** @var \\App\\Signer|null */ private $signer; }',
            'namespace App; interface Signer { function sign(); } /** Documents \\App\\Signer. */ class Signed {}',
            ['no_redundant_local_interfaces'],
        ];
        yield 'Scout: construction method is a dispatched factory hook' => [
            'class Manager { function driver($driver) { $method = "create".ucfirst($driver)."Driver"; return $this->$method(); } } class Engines extends Manager { protected function createNullDriver() { return new Product(); } }',
            'class Manager { function driver($driver) { return $driver; } } class Engines extends Manager { protected function createNullDriver() { return new Product(); } }',
            ['no_trivial_factories'],
        ];
        yield 'Scout: prepared settings are guarded and forwarded' => [
            'function sync($engine, array $settings) { $settings = $engine->configureSoftDeleteFilter($settings); if ($settings) { $engine->updateIndexSettings("products", $settings); } }',
            'function sync($engine) { $ready = $engine->isReady(); if ($ready) { $engine->updateIndexSettings("products", []); } }',
            ['max_ask_then_tell_patterns'],
        ];
        yield 'Serializable Closure: native stream registration' => [
            'class Stream { public function stream_open($path, $mode, $options, &$opened) {} public static function register() { stream_wrapper_register("test", __CLASS__); } }',
            'class Stream { public function stream_open($path, $mode, $options, &$opened) {} }',
            ['no_unused_parameters', 'max_arguments'],
        ];
    }

    private function findings(string $source, array $rules): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-laravel-regression-');
        file_put_contents($path, '<?php '.$source);

        try {
            $checks = [];
            foreach ($rules as $id) {
                $entry = RuleCatalog::definitions()[$id];
                $class = $entry['class'];
                $checks[] = new $class(...$entry['arguments']);
            }
            $result = (new Runner)->check([$path], Soda::configure()->with($checks));

            return array_map(static fn ($violation) => $violation->rule, $result->violations->all());
        } finally {
            unlink($path);
        }
    }
}
