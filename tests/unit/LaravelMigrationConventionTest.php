<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Architecture\NoAnonymousClasses;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class LaravelMigrationConventionTest extends TestCase
{
    public function testAllowsAnonymousLaravelMigrationAndItsRequiredHooks(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
PHP));
    }

    public function testAllowsNamedMigrationHooks(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration {
    public function up(): void {}
    public function down(): void {}
}
PHP));
    }

    public function testStillReportsUnrelatedAnonymousClass(): void
    {
        self::assertSame(['no_anonymous_classes'], $this->ruleIds(<<<'PHP'
<?php
return new class {
    public function run(): void {}
};
PHP));
    }

    public function testStillReportsIncompleteAnonymousMigration(): void
    {
        self::assertSame(['no_anonymous_classes', 'method_name_length'], $this->ruleIds(<<<'PHP'
<?php
return new class extends Migration {
    public function up(): void {}
};
PHP));
    }

    public function testDoesNotTrustAnApplicationClassMerelyNamedMigration(): void
    {
        self::assertSame(['no_anonymous_classes', 'method_name_length'], $this->ruleIds(<<<'PHP'
<?php
abstract class Migration {}

return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
PHP));
    }

    public function testStillReportsShortMethodOutsideMigration(): void
    {
        self::assertSame(['method_name_length'], $this->ruleIds(<<<'PHP'
<?php
class Workflow {
    public function up(): void {}
}
PHP));
    }

    public function testStillReportsOtherShortMethodOnMigration(): void
    {
        self::assertSame(['method_name_length'], $this->ruleIds(<<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration {
    public function up(): void {}
    public function down(): void {}
    public function go(): void {}
}
PHP));
    }

    /** @return list<string> */
    private function ruleIds(string $code): array
    {
        return array_map(
            static fn (Violation $violation): string => $violation->rule,
            $this->violations($code),
        );
    }

    /** @return list<Violation> */
    private function violations(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-laravel-migration-');
        self::assertIsString($file);
        file_put_contents($file, $code);

        try {
            $architecture = CheckFixture::collect($file, [new NoAnonymousClasses])['violations'];
            $naming = CheckFixture::forRule(new MethodNameLength(min: 3, max: 32), $this->context($file))->all();

            return [...$architecture, ...$naming];
        } finally {
            unlink($file);
        }
    }

    private function context(string $file): array
    {
        return [CheckFixture::checks(), [
            $file => [
                'file_loc'      => 1,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ]];
    }
}
