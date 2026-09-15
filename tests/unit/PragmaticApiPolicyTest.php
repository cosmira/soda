<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use PHPUnit\Framework\TestCase;

final class PragmaticApiPolicyTest extends TestCase
{
    public function testConventionsAreAcceptedByDefaultAndStrictPoliciesRemainAvailable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-conventions-');
        file_put_contents($file, <<<'SOURCE'
<?php
namespace Package\Models;
class Record {
    protected static string $suffix = '_draft';
    protected static string $key = 'id';
    protected static string $revision = 'updated_at';
    protected static array $columns = [];
    private ?string $activeTable = null;
    private ?string $resolvedTable = null;
    public function supportsDraft(): bool { return true; }
    public function registry(): \Package\Support\Registry { return new \Package\Support\Registry($this); }
}
namespace Package\Support;
class Registry {
    private \Package\Models\Record $record;
    public function __construct(?\Package\Models\Record $record = null) {
        $this->record = $record ?? app(\Package\Models\Record::class);
    }
    public function accepts(object $model): bool { return method_exists($model, 'useSandbox'); }
}
SOURCE);
        $policies = ['max_properties_per_class', 'no_dependency_cycles', 'no_service_locator_calls', 'no_runtime_contract_probes', 'no_static_mutable_properties', 'boolean_methods_without_prefix'];

        try {
            $standard = (new Runner)->check([$file], Soda::configure()->with(RuleCatalog::standard()));
            self::assertSame([], array_values(array_intersect($policies, $standard->violations->pluck('rule')->all())));
            $checks = [];
            foreach ($policies as $id) {
                $entry = RuleCatalog::definitions()[$id];
                $class = $entry['class'];
                $checks[] = new $class(...$entry['arguments']);
            }
            $strict = (new Runner)->check([$file], Soda::configure()->with($checks));
            foreach ($policies as $id) {
                self::assertContains($id, $strict->violations->pluck('rule')->all());
            }
        } finally {
            unlink($file);
        }
    }
}
