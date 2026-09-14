<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class EffectiveInheritedPublicMethodsTest extends TestCase
{
    public function testReportsInheritedApiInAdversarialWorkflows(): void
    {
        $candidate = dirname(__DIR__, 2).'/demo-app/candidates/63-inherited-public-api';
        $files = glob($candidate.'/*.php');
        self::assertIsArray($files);

        $violations = $this->publicMethodViolations($files, dirname($candidate, 2).'/soda.php');

        self::assertCount(3, $violations);
        self::assertSame([21, 21, 21], array_map(
            static fn (Violation $violation): int => ['value' => $violation->value, 'threshold' => $violation->threshold]['value'],
            $violations,
        ));
    }

    public function testCombinesParentTraitAndChildApi(): void
    {
        $violations = $this->analyseSources([
            <<<'PHP'
<?php
class BaseWorkflow
{
    public function alpha(): void {}
    public function beta(): void {}
}
PHP,
            <<<'PHP'
<?php
trait Auditable
{
    public function audit(): void {}
}
PHP,
            <<<'PHP'
<?php
final class Workflow extends BaseWorkflow
{
    use Auditable;

    public function commit(): void {}
}
PHP,
        ]);

        self::assertCount(1, $violations);
        self::assertSame(4, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
    }

    public function testOverrideIsDeduplicatedAtConfiguredLimit(): void
    {
        self::assertSame([], $this->analyseSources([
            <<<'PHP'
<?php
class BaseWorkflow
{
    public function alpha(): void {}
    public function beta(): void {}
}
PHP,
            <<<'PHP'
<?php
final class Workflow extends BaseWorkflow
{
    public function alpha(): void {}
    public function commit(): void {}
}
PHP,
        ]));
    }

    public function testDuplicateNamesKeepFirstMembersAndFillMissingParent(): void
    {
        $violations = $this->analyseSources([
            '<?php class BaseWorkflow { public function alpha() {} public function beta() {} public function gamma() {} }',
            '<?php class Workflow { public function delta() {} }',
            '<?php class Workflow extends BaseWorkflow { public function ignored() {} }',
        ]);

        self::assertSame([4, 4], array_map(
            static fn (Violation $violation): int => $violation->value,
            $violations,
        ));
    }

    public function testUsesOnlyKnownApiForExternalParent(): void
    {
        self::assertSame([], $this->analyseSources([<<<'PHP'
<?php
final class Workflow extends VendorWorkflow
{
    public function alpha(): void {}
    public function beta(): void {}
    public function commit(): void {}
}
PHP]));
    }

    /**
     * @param list<string> $sources
     *
     * @return list<Violation>
     */
    private function analyseSources(array $sources): array
    {
        $files = [];
        $temporary = tempnam(sys_get_temp_dir(), 'soda-public-api-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxPublicMethods;

return Soda::configure()->with([new MaxPublicMethods(3)]);
PHP);

        try {
            foreach ($sources as $source) {
                $file = tempnam(sys_get_temp_dir(), 'soda-inherited-api-');
                self::assertIsString($file);
                file_put_contents($file, $source);
                $files[] = $file;
            }

            return $this->publicMethodViolations($files, $config);
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }

            unlink($config);
        }
    }

    /**
     * @param list<string> $files
     *
     * @return list<Violation>
     */
    private function publicMethodViolations(array $files, string $config): array
    {
        return Analyzer::analyze($files, $config)->violations
            ->where('rule', 'max_public_methods')
            ->values()
            ->all();
    }
}
