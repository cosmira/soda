<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;
use PHPUnit\Framework\TestCase;

final class ReadonlyDataConstructorArgumentsTest extends TestCase
{
    public function testAllowsPublicPromotedStateOnFinalReadonlyDataCarrier(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
final readonly class ShipmentData
{
    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        public string $date,
    ) {}
}
PHP));
    }

    public function testStillReportsBehavioralMethod(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class ShipmentData
{
    public function ship(string $order, string $address, string $service, string $date): void {}
}
PHP));
    }

    public function testStillReportsMutableDataConstructor(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final class ShipmentData
{
    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        public string $date,
    ) {}
}
PHP));
    }

    public function testStillReportsReadonlyServiceDependencies(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class CheckoutService
{
    public function __construct(
        private Gateway $gateway,
        private Inventory $inventory,
        private Mailer $mailer,
        private Logger $logger,
    ) {}
}
PHP));
    }

    public function testStillReportsMixedConstructorInputs(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class ShipmentData
{
    public string $normalizedDate;

    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        string $date,
    ) {
        $this->normalizedDate = $date;
    }
}
PHP));
    }

    public function testStillReportsNonFinalReadonlyConstructor(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
readonly class ShipmentData
{
    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        public string $date,
    ) {}
}
PHP));
    }

    public function testExplicitShapePolicyStillLimitsReadonlyDataShape(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class ShipmentData
{
    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        public string $date,
        public string $carrier,
        public string $warehouse,
    ) {}
}
PHP));
    }

    public function testDoesNotExemptDataConstructorWithoutShapePolicy(): void
    {
        self::assertCount(1, $this->violationsWithOnlyMaxArguments(<<<'PHP'
<?php
final readonly class ShipmentData
{
    public function __construct(
        public string $order,
        public string $address,
        public string $service,
        public string $date,
    ) {}
}
PHP));
    }

    /** @return list<Violation> */
    private function violationsWithOnlyMaxArguments(string $code): array
    {
        $temporary = tempnam(sys_get_temp_dir(), 'soda-max-arguments-config-');
        self::assertIsString($temporary);
        $config = $temporary.'.php';
        rename($temporary, $config);
        file_put_contents($config, <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxArguments;

return Soda::configure()->with([new MaxArguments(3)]);
PHP);

        try {
            return $this->violations($code, $config);
        } finally {
            unlink($config);
        }
    }

    /** @return list<Violation> */
    private function violations(string $code, ?string $config = null): array
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-readonly-data-args-');
        self::assertIsString($file);
        file_put_contents($file, $code);

        try {
            $result = $config !== null
                ? Analyzer::analyze([$file], $config)
                : (new Runner)->check([$file], Soda::configure()->with([
                    new MaxArguments(3, new MaxPropertiesPerClass(5)),
                ]));

            return $result->violations
                ->where('rule', 'max_arguments')
                ->values()
                ->all();
        } finally {
            unlink($file);
        }
    }
}
