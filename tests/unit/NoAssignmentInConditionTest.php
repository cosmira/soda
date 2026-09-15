<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoAssignmentInConditionTest extends TestCase
{
    public function testCallableBodiesDoNotBelongToTheOuterCondition(): void
    {
        foreach ([
            'if (accept(function () { $value = nextValue(); })) {}',
            'if (accept(fn () => $value = nextValue())) {}',
            'if (new class { function run() { $value = nextValue(); } }) {}',
            'if ((function () { $value = nextValue(); return $value; })()) {}',
        ] as $source) {
            $file = $this->tempFile('<?php '.$source);

            try {
                $violations = CheckFixture::forRule(new NoAssignmentInCondition, $this->context([$file => $this->minimalMetrics()]));
                self::assertCount(0, $violations, $source);
            } finally {
                unlink($file);
            }
        }
    }

    public function testNestedCallableConditionsAreStillCheckedIndependently(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if (accept(function () {
    $value = nextValue();
    if ($inner = nextValue()) { consume($inner); }
}, $argument = nextValue())) {}
PHP);

        try {
            $violations = CheckFixture::forRule(new NoAssignmentInCondition, $this->context([$file => $this->minimalMetrics()]));
            self::assertSame([4, 5], $violations->map(static fn ($violation): ?int => $violation->line)->all());
        } finally {
            unlink($file);
        }
    }

    public function testCheckReportsAssignmentInsideIfCondition(): void
    {
        $file = $this->tempFile("<?php\nif (\$value = nextValue()) {\n    echo \$value;\n}\n");
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        $this->assertSame('no_assignment_in_condition', $violations->first()->rule);
        $this->assertStringContainsString('Assignment inside condition', (string) $violations->first()->message);
        unlink($file);
    }

    public function testCheckReportsAssignmentOperationInsideWhileCondition(): void
    {
        $file = $this->tempFile("<?php\nwhile (\$offset += step()) {\n    echo \$offset;\n}\n");
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(1, $violations);
        unlink($file);
    }

    public function testCheckReportsAssignmentsInsideEverySupportedConditionForm(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if ($ready) {
    echo 'ready';
} elseif ($elseifValue = nextValue()) {
    echo $elseifValue;
}

do {
    echo 'loop';
} while ($doValue = nextValue());

for (; $forValue = nextValue(); ) {
    echo $forValue;
}

$result = ($ternaryValue = nextValue()) ? 'yes' : 'no';
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertSame([4, 10, 12, 16], $violations->map(static fn ($violation): ?int => $violation->line)->all());
        unlink($file);
    }

    public function testCheckAllowsSeparateAssignmentBeforeCondition(): void
    {
        $file = $this->tempFile("<?php\n\$value = nextValue();\nif (\$value !== null) {\n    echo \$value;\n}\n");
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testCheckAllowsAssignmentsInForInitAndLoopExpressions(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
for ($i = 0; $i < 10; $i += step()) {
    echo $i;
}
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testCheckReportsNestedAssignmentInsideBooleanCondition(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if ($ready && ($value = nextValue()) !== null) {
    echo $value;
}
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertSame([2], $violations->map(static fn ($violation): ?int => $violation->line)->all());
        unlink($file);
    }

    public function testCheckAllowsAssignmentsInsideBranchBody(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if ($ready) {
    $value = nextValue();
    echo $value;
}
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertCount(0, $violations);
        unlink($file);
    }

    public function testCheckReportsOneViolationForMultipleAssignmentsOnSameConditionLine(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if (($left = nextValue()) && ($right = nextValue())) {
    echo $left.$right;
}
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertSame([2], $violations->map(static fn ($violation): ?int => $violation->line)->all());
        unlink($file);
    }

    public function testCheckReportsSeparateViolationsForAssignmentsOnDifferentConditionLines(): void
    {
        $file = $this->tempFile(<<<'PHP'
<?php
if (
    ($left = nextValue())
    && ($right = nextValue())
) {
    echo $left.$right;
}
PHP);
        $rule = new NoAssignmentInCondition;

        $violations = CheckFixture::forRule($rule, $this->context([$file => $this->minimalMetrics()]));

        $this->assertSame([3, 4], $violations->map(static fn ($violation): ?int => $violation->line)->all());
        unlink($file);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalMetrics(): array
    {
        return [
            'file_loc'       => 1,
            'classes_count'  => 0,
            'classes'        => [],
            'methods'        => [],
            'namespaces'     => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $qualityMetrics
     */
    private function context(array $qualityMetrics): array
    {
        $core = $qualityMetrics;
        $fileMetrics = $core;

        return [CheckFixture::checks(), $fileMetrics];
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_assignment_condition_');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
