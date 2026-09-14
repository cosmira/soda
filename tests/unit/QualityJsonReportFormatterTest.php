<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use Cosmira\Soda\Config\RuleCatalog;
use PHPUnit\Framework\TestCase;

final class QualityJsonReportFormatterTest extends TestCase
{
    public function testFormatsQualityReportSchemaVersionAndGateResult(): void
    {
        $data = (new QualityJsonReportFormatter)->format(new QualityResult([]));

        $this->assertSame(4, $data['schema_version']);
        $this->assertTrue($data['passed']);
        $this->assertArrayNotHasKey('metrics', $data);
        $this->assertArrayHasKey('violations', $data);
        $this->assertSame([], $data['violations']);
        $this->assertArrayNotHasKey('directories', $data);
        $this->assertArrayNotHasKey('files', $data);
        $this->assertArrayNotHasKey('loc', $data);
        $this->assertArrayNotHasKey('complexity', $data);
        $this->assertArrayNotHasKey('errors', $data);
    }

    public function testFormatsFailingQualityReportWithViolations(): void
    {
        $firstViolation = new Violation(rule: 'max_line_length', file: '/path/to/file.php', value: 121, threshold: 120, line: 7, message: 'Line is too long.');
        $secondViolation = new Violation(rule: 'custom_rule', file: '/path/to/other.php', value: 2, threshold: 1, line: 11, message: 'Second finding.');

        $data = (new QualityJsonReportFormatter)->format(new QualityResult([
            $firstViolation,
            $secondViolation,
        ]));

        $this->assertSame(4, $data['schema_version']);
        $this->assertFalse($data['passed']);
        $this->assertSame(
            [
                [...$firstViolation->toArray(), 'recommendation' => RuleCatalog::definitions()['max_line_length']['advice']],
                [...$secondViolation->toArray(), 'recommendation' => null],
            ],
            $data['violations'],
        );
    }

    public function testFormatsNamespaceNameLengthViolationEvidence(): void
    {
        $violation = new Violation(rule: 'namespace_name_length', file: '/path/to/file.php', value: 1, threshold: 3, line: 3, message: 'Name "X" has length 1.');

        $data = (new QualityJsonReportFormatter)->format(new QualityResult([
            $violation,
        ]));

        $this->assertSame([
            'rule'           => 'namespace_name_length',
            'file'           => '/path/to/file.php',
            'method'         => null,
            'class'          => null,
            'line'           => 3,
            'value'          => 1,
            'threshold'      => 3,
            'message'        => 'Name "X" has length 1.',
            'recommendation' => RuleCatalog::definitions()['namespace_name_length']['advice'],
        ], $data['violations'][0]);
    }
}
