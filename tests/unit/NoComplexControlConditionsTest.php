<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class NoComplexControlConditionsTest extends TestCase
{
    public function testReportsMoreThanOneComparison(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
if ($a > $b && $a > $c) {
    process($a);
}
PHP);

        $this->assertSame(
            [['line' => 2, 'reason' => 'multiple_operations']],
            $result['metrics']['complexControlConditions'],
        );
        $this->assertStringContainsString('at most one', (string) $result['violations'][0]->message);
    }

    public function testReportsQueryWorkInsideWhileCondition(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
while (File::query()->where('status', File::STATUS_NEW)->count() >= $this->threshold()) {
    processNextFile();
}
PHP);

        $this->assertSame(
            [['line' => 2, 'reason' => 'multiple_operations']],
            $result['metrics']['complexControlConditions'],
        );
        $this->assertStringContainsString('at most one', (string) $result['violations'][0]->message);
    }

    public function testAllowsNamedPredicateAndDebuggableIntermediateValue(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
while ($this->hasTooManyNewFiles()) {
    processNextFile();
}

$count = File::query()->where('status', File::STATUS_NEW)->count();
if ($count >= $secret) {
    processNextFile();
}
PHP);

        $this->assertSame([], $result['metrics']['complexControlConditions']);
        $this->assertSame([], $result['violations']);
    }

    public function testAllowsLongHomogeneousBooleanLogicOutsideControlFlow(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
function isArrayLike(string $line): bool
{
    return str_ends_with($line, ',')
        || str_ends_with($line, '],')
        || str_ends_with($line, ');')
        || str_contains($line, ' => ');
}
PHP);

        $this->assertSame([], $result['metrics']['complexControlConditions']);
        $this->assertSame([], $result['violations']);
    }

    public function testAllowsAtMostOneDecisionOperation(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
if ($a > $b) {
    process();
}

if ($enabled && $readable) {
    process();
}

if (!$disabled) {
    process();
}

while ($this->isReady()) {
    process();
}
PHP);

        $this->assertSame([], $result['metrics']['complexControlConditions']);
        $this->assertSame([], $result['violations']);
    }

    public function testReportsCallInsideComparisonAndRepeatedLogicalOperations(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
if ($this->age() > 18) {
    process();
}

if ($enabled && $readable && $owned) {
    process();
}
PHP);

        $this->assertSame(
            [
                ['line' => 2, 'reason' => 'multiple_operations'],
                ['line' => 6, 'reason' => 'multiple_operations'],
            ],
            $result['metrics']['complexControlConditions'],
        );
        $this->assertCount(2, $result['violations']);
    }

    public function testCoversEverySupportedControlConditionForm(): void
    {
        $result = $this->analyse(<<<'PHP'
<?php
if ($first) {
    process();
} elseif ($a && ($b || $c)) {
    process();
}

do {
    process();
} while (Queue::query()->ready()->exists());

for (; $a && ($b || $c); ) {
    process();
}

$result = $a && ($b || $c) ? 'yes' : 'no';
PHP);

        $this->assertSame(
            [
                ['line' => 4, 'reason' => 'multiple_operations'],
                ['line' => 10, 'reason' => 'multiple_operations'],
                ['line' => 12, 'reason' => 'multiple_operations'],
                ['line' => 16, 'reason' => 'multiple_operations'],
            ],
            $result['metrics']['complexControlConditions'],
        );
        $this->assertCount(4, $result['violations']);
    }

    /** @return array{metrics: array, violations: array} */
    private function analyse(string $source): array
    {
        $path = tempnam(sys_get_temp_dir(), 'soda_complex_condition_');
        $this->assertNotFalse($path);
        file_put_contents($path, $source);

        try {
            $result = CheckFixture::collect($path, [new NoComplexControlConditions]);
        } finally {
            unlink($path);
        }

        return ['metrics' => $result['metrics'], 'violations' => $result['violations']];
    }
}
