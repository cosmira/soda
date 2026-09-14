<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Architecture\NoBooleanParameters;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('explicitStateCases')]
    public function testRecognizesExplicitStateWithoutExemptingBehavior(string $declaration, int $expected): void
    {
        self::assertCount($expected, $this->violations('<?php '.$declaration));
    }

    public static function explicitStateCases(): iterable
    {
        yield 'promoted state also controls behavior' => ['final readonly class Preference { public function __construct(public bool $enabled) { if ($enabled) send(); } }', 1];
        yield 'promoted flag forwarded' => ['final readonly class Preference { public function __construct(public bool $enabled) { setup($enabled); } }', 1];
        yield 'readonly class' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; } }', 0];
        yield 'readonly property' => ['final class Preference { private readonly bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; } }', 0];
        yield 'nullable state' => ['final readonly class Preference { public ?bool $enabled; public function __construct(?bool $enabled) { $this->enabled = $enabled; } }', 0];
        yield 'union state' => ['final readonly class Preference { public bool|string $enabled; public function __construct(bool|string $enabled) { $this->enabled = $enabled; } }', 0];
        yield 'different property name' => ['final readonly class Preference { public bool $active; public function __construct(bool $enabled) { $this->active = $enabled; } }', 0];
        yield 'unrelated constructor work' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { setup(); $this->enabled = $enabled; } }', 0];
        yield 'behavior after assignment' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; if ($enabled) send(); } }', 1];
        yield 'transformed value' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = !$enabled; } }', 1];
        yield 'forwarded flag' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; setup($enabled); } }', 1];
        yield 'mutable field' => ['final class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; } }', 1];
        yield 'undeclared field' => ['final readonly class Preference { public function __construct(bool $enabled) { $this->enabled = $enabled; } }', 1];
        yield 'other object field' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $other->enabled = $enabled; } }', 1];
        yield 'dynamic field' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->{$property} = $enabled; } }', 1];
        yield 'assignment by reference' => ['final readonly class Preference { public bool $enabled; public function __construct(bool &$enabled) { $this->enabled = $enabled; } }', 1];
        yield 'variadic argument' => ['final readonly class Preference { public array $enabled; public function __construct(bool ...$enabled) { $this->enabled = $enabled; } }', 1];
        yield 'parameter overwritten' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $enabled = false; $this->enabled = $enabled; } }', 1];
        yield 'closure capture' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $this->enabled = $enabled; $later = function () use ($enabled) {}; } }', 1];
        yield 'nested assignment' => ['final readonly class Preference { public bool $enabled; public function __construct(bool $enabled) { $later = function () use ($enabled) { $this->enabled = $enabled; }; } }', 1];
        yield 'ordinary method' => ['final readonly class Preference { public bool $enabled; public function initialize(bool $enabled) { $this->enabled = $enabled; } }', 1];
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
