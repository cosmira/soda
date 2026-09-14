<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

/**
 * Formats the public `soda quality --report-json` payload.
 */
final readonly class QualityJsonReportFormatter
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private ?RuleMetadata $ruleMetadata = null,
    ) {}

    /**
     * @return array{schema_version: 4, passed: bool, violations: list<array<string, mixed>>}
     */
    public function format(QualityResult $result): array
    {
        $metadata = $this->ruleMetadata ?? RuleMetadata::default();

        return [
            'schema_version' => 4,
            'passed'         => $result->isPassing(),
            'violations'     => $result->violations->map(function (Violation $violation) use ($metadata): array {
                return [
                    ...$violation->toArray(),
                    'recommendation' => $metadata->advice($violation->rule),
                ];
            })->all(),
        ];
    }
}
