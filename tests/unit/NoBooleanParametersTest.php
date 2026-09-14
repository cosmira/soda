<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Architecture\NoBooleanParameters;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoBooleanParametersTest extends TestCase
{
    public function testAllowsPromotedBooleanStateInReadonlyValueObject(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
final readonly class Preference
{
    public function __construct(private bool $enabled) {}
}
PHP));
    }

    public function testAllowsNullablePromotedStateInReadonlyValueObject(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
final readonly class InheritedPreference
{
    public function __construct(public ?bool $enabled) {}
}
PHP));
    }

    public function testAllowsExplicitReadonlyPromotedProperty(): void
    {
        self::assertSame([], $this->violations(<<<'PHP'
<?php
final class Preference
{
    public function __construct(public readonly bool $enabled) {}
}
PHP));
    }

    public function testStillReportsBehavioralBooleanParameter(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class Sender
{
    public function send(bool $audit): void {}
}
PHP));
    }

    public function testStillReportsPromotedStateOnMutableObject(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final class Preference
{
    public function __construct(public bool $enabled) {}
}
PHP));
    }

    public function testStillReportsOrdinaryConstructorArgument(): void
    {
        self::assertCount(1, $this->violations(<<<'PHP'
<?php
final readonly class Preference
{
    public function __construct(bool $enabled) {}
}
PHP));
    }

    /** @return list<Violation> */
    private function violations(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-boolean-parameter-');
        self::assertIsString($file);
        file_put_contents($file, $code);

        try {
            return CheckFixture::collect($file, [new NoBooleanParameters])['violations'];
        } finally {
            unlink($file);
        }
    }
}
