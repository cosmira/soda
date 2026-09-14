<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use PHPUnit\Framework\TestCase;

final class EffectivePropertyCountTest extends TestCase
{
    public function testReportsInheritedPromotedStateInAdversarialRequests(): void
    {
        $candidate = dirname(__DIR__, 2).'/demo-app/candidates/62-inherited-readonly-state';
        $files = glob($candidate.'/*.php');
        self::assertIsArray($files);

        $violations = $this->propertyViolations($files);

        self::assertCount(3, $violations);
        self::assertSame([6, 6, 6], array_map(
            static fn (Violation $violation): int => ['value' => $violation->value, 'threshold' => $violation->threshold]['value'],
            $violations,
        ));
    }

    public function testCountsPromotedPropertiesWithoutInheritance(): void
    {
        $violations = $this->analyseSources([<<<'PHP'
<?php
final readonly class WideData
{
    public function __construct(
        public string $first,
        public string $second,
        public string $third,
        public string $fourth,
        public string $fifth,
        public string $sixth,
    ) {}
}
PHP]);

        self::assertCount(1, $violations);
        self::assertSame(6, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
    }

    public function testCountsTraitComposedState(): void
    {
        $violations = $this->analyseSources([
            <<<'PHP'
<?php
trait RequestMetadata
{
    protected readonly string $requestId;
    protected readonly string $actorId;
    protected readonly string $locale;
}
PHP,
            <<<'PHP'
<?php
final readonly class WideData
{
    use RequestMetadata;

    public function __construct(
        public string $first,
        public string $second,
        public string $third,
    ) {}
}
PHP,
        ]);

        self::assertCount(1, $violations);
        self::assertSame(6, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
    }

    public function testAllowsInheritedStateAtConfiguredLimit(): void
    {
        self::assertSame([], $this->analyseSources([
            <<<'PHP'
<?php
readonly class BaseData
{
    public function __construct(public string $first, public string $second) {}
}
PHP,
            <<<'PHP'
<?php
final readonly class ExactData extends BaseData
{
    public function __construct(
        public string $third,
        public string $fourth,
        public string $fifth,
    ) {
        parent::__construct('first', 'second');
    }
}
PHP,
        ]));
    }

    public function testUsesOnlyKnownStateForExternalParent(): void
    {
        self::assertSame([], $this->analyseSources([<<<'PHP'
<?php
final readonly class LocalData extends VendorData
{
    public function __construct(
        public string $first,
        public string $second,
        public string $third,
    ) {}
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

        try {
            foreach ($sources as $source) {
                $file = tempnam(sys_get_temp_dir(), 'soda-effective-properties-');
                self::assertIsString($file);
                file_put_contents($file, $source);
                $files[] = $file;
            }

            return $this->propertyViolations($files);
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * @param list<string> $files
     *
     * @return list<Violation>
     */
    private function propertyViolations(array $files): array
    {
        return Analyzer::analyze(
            $files,
            dirname(__DIR__, 2).'/demo-app/soda.php',
        )->violations
            ->where('rule', 'max_properties_per_class')
            ->values()
            ->all();
    }
}
