<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use PHPUnit\Framework\TestCase;

final class QualityResultTest extends TestCase
{
    public function testIsPassingForEmptyViolationListAndCollection(): void
    {
        $fromList = new QualityResult([]);
        $fromCollection = new QualityResult(collect());

        $this->assertTrue($fromList->isPassing());
        $this->assertTrue($fromCollection->isPassing());
    }

    public function testIsFailingForNonEmptyViolationListAndCollection(): void
    {
        $violation = new Violation(rule: 'custom_rule', file: '/path/to/file.php', value: 1, threshold: 0);

        $fromList = new QualityResult([$violation]);
        $fromCollection = new QualityResult(collect([$violation]));

        $this->assertFalse($fromList->isPassing());
        $this->assertFalse($fromCollection->isPassing());
        $this->assertSame([$violation], $fromList->violations->all());
        $this->assertSame([$violation], $fromCollection->violations->all());
    }
}
