<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class EffectiveInterfaceCountTest extends TestCase
{
    public function testReportsInheritedRolesInAdversarialProcesses(): void
    {
        $candidate = dirname(__DIR__, 2).'/demo-app/candidates/64-inherited-interface-roles';
        $files = glob($candidate.'/*.php');
        self::assertIsArray($files);

        $violations = $this->interfaceViolations($files, dirname($candidate, 2).'/soda.php');

        self::assertCount(3, $violations);
        self::assertSame([6, 6, 6], array_map(
            static fn (Violation $violation): int => ['value' => $violation->value, 'threshold' => $violation->threshold]['value'],
            $violations,
        ));
    }

    public function testExpandsTransitiveInterfaceInheritance(): void
    {
        $violations = $this->analyseSources([
            '<?php interface RootRole {}',
            '<?php interface ChildRole extends RootRole {}',
            '<?php interface Auditable {}',
            '<?php interface Exportable {}',
            '<?php final class Process implements ChildRole, Auditable, Exportable {}',
        ]);

        self::assertCount(1, $violations);
        self::assertSame(4, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
    }

    public function testDeduplicatesInterfaceRepeatedByChild(): void
    {
        self::assertSame([], $this->analyseSources([
            '<?php interface Auditable {}',
            '<?php interface Exportable {}',
            '<?php interface Searchable {}',
            '<?php class BaseProcess implements Auditable, Exportable {}',
            '<?php final class Process extends BaseProcess implements Auditable, Searchable {}',
        ]));
    }

    public function testCountsDirectExternalContractsByDeclaredName(): void
    {
        $violations = $this->analyseSources([
            '<?php final class Process implements VendorOne, VendorTwo, VendorThree, VendorFour {}',
        ]);

        self::assertCount(1, $violations);
        self::assertSame(4, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
    }

    public function testDoesNotInventContractsFromExternalParent(): void
    {
        self::assertSame([], $this->analyseSources([
            '<?php interface FirstRole {}',
            '<?php interface SecondRole {}',
            '<?php interface ThirdRole {}',
            '<?php final class Process extends VendorProcess implements FirstRole, SecondRole, ThirdRole {}',
        ]));
    }

    /**
     * @param list<string> $sources
     *
     * @return list<Violation>
     */
    private function analyseSources(array $sources): array
    {
        $files = [];
        $temporary = tempnam(sys_get_temp_dir(), 'soda-interface-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxInterfacesPerClass;

return Soda::configure()->with([new MaxInterfacesPerClass(3)]);
PHP);

        try {
            foreach ($sources as $source) {
                $file = tempnam(sys_get_temp_dir(), 'soda-effective-interfaces-');
                self::assertIsString($file);
                file_put_contents($file, $source);
                $files[] = $file;
            }

            return $this->interfaceViolations($files, $config);
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
    private function interfaceViolations(array $files, string $config): array
    {
        return Analyzer::analyze($files, $config)->violations
            ->where('rule', 'max_interfaces_per_class')
            ->values()
            ->all();
    }
}
