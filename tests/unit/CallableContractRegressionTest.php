<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoDynamicInvocation;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallableContractRegressionTest extends TestCase
{
    #[DataProvider('callbacks')]
    public function testNativeCallbackContracts(string $body, int $expected): void
    {
        self::assertCount($expected, $this->findings(['<?php '.$body], new NoDynamicInvocation));
    }

    public static function callbacks(): iterable
    {
        yield 'native callback' => ['function run(Closure $callback) { $callback(); }', 0];
        yield 'documented callback' => ['/** @param callable $callback */ function run($callback) { $callback(); }', 0];
        yield 'unresolved callback is not evidence' => ['function run($callback) { $callback(); }', 0];
        yield 'external invokable type is not evidence' => ['function run(ExternalHandler $callback) { $callback(); }', 0];
        yield 'unknown property is not evidence' => ['function run($object) { ($object->callback)(); }', 0];
        yield 'unknown method result is not evidence' => ['function run($object) { ($object->callback())(); }', 0];
        yield 'unknown collection is not evidence' => ['function run($items) { foreach ($items as $callback) { $callback(); } }', 0];
        yield 'reassignment is not evidence' => ['function run(Closure $callback, $value) { $callback = $value; $callback(); }', 0];
        yield 'framework name does not change policy' => ['function run(\\App\\Pipeline $pipeline, $next) { $next(); }', 0];
        yield 'computed method is evidence' => ['function run($object, $method) { $object->$method(); }', 1];
        yield 'class selection does not require a factory' => ['function run($class) { new $class(); }', 0];
        yield 'indirect helper is evidence' => ['function run($callback) { call_user_func($callback); }', 1];
    }

    public function testTraitOverrideRetainsAncestorArityButExtraInputsRemainViolations(): void
    {
        $sources = [
            '<?php namespace Vendor; trait Relations { protected function relation($a, $b, $c, $d) {} } class Model { use Relations; }',
            '<?php namespace App; trait CustomRelations { protected function relation($a, $b, $c, $d) {} }',
            '<?php namespace App; class Model extends \Vendor\Model { use CustomRelations; }',
        ];
        $findings = $this->findings($sources, new MaxArguments(3));
        self::assertSame(['Vendor\Relations::relation'], array_column($findings, 'method'));
        self::assertSame(['Vendor\Relations::relation'], array_column($this->findings(array_reverse($sources), new MaxArguments(3)), 'method'));
        $sources[1] = '<?php namespace App; trait CustomRelations { protected function relation($a, $b, $c, $d, $extra) {} }';
        self::assertCount(2, $this->findings($sources, new MaxArguments(3)));
        $sources[2] = '<?php namespace App; class Model { use CustomRelations; }';
        self::assertCount(2, $this->findings($sources, new MaxArguments(3)));
    }

    private function findings(array $sources, Check $rule): array
    {
        $files = [];

        try {
            foreach ($sources as $source) {
                $file = tempnam(sys_get_temp_dir(), 'soda-callback-');
                file_put_contents($file, $source);
                $files[] = $file;
            }

            return (new Runner)->check($files, Soda::configure()->with([$rule]))->violations->filter(static fn ($finding): bool => $finding->rule === $rule->id())->values()->all();
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
