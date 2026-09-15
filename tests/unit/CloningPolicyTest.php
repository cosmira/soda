<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CloningPolicyTest extends TestCase
{
    #[DataProvider('copies')]
    public function testStandardRulesDoNotTreatCopyingAsAProblem(string $source, int $nestedConditions = 0): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-copy-');
        file_put_contents($path, '<?php '.$source);

        try {
            $result = (new Runner)->check([$path], Soda::configure()->with(RuleCatalog::standard()));
            self::assertNotContains('no_object_cloning', $result->violations->pluck('rule')->all());
            self::assertCount($nestedConditions, $result->violations->where('rule', 'no_complex_control_conditions'));
        } finally {
            unlink($path);
        }
    }

    public static function copies(): iterable
    {
        yield 'query builder forks' => ['function run(\Illuminate\Database\Query\Builder $query) { while ((clone $query)->exists()) { $ready = (clone $query)->whereNull("parent_id"); consume($ready); } }'];
        yield 'nested factory is still computation' => ['function run() { if ((clone createQuery())->exists()) { consume(); } }', 1];
        yield 'value copy' => ['class Options { public int $limit; function limitedTo(int $limit): self { $copy = clone $this; $copy->limit = $limit; return $copy; } }'];
        yield 'unknown type is not proof of danger' => ['function copyValue($value) { return clone $value; }'];
    }

    public function testDefaultConfigurationCannotReintroduceTheBan(): void
    {
        self::assertFalse(RuleCatalog::definitions()['no_object_cloning']['standard']);
        self::assertNotContains('no_object_cloning', array_map(fn ($rule) => $rule->id(), RuleCatalog::checks('structural')));
        self::assertStringNotContainsString('NoObjectCloning', SodaInitFileEmitter::emit());
    }
}
