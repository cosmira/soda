<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

final readonly class Violation
{
    /**
     * Carry a measured result and its source location without intermediate builders.
     *
     * @param string  $rule      Stable identifier of the rule producing the diagnostic.
     * @param string  $file      Source file containing the reported declaration.
     * @param int     $value     Measured value reported by the rule.
     * @param int     $threshold Threshold used to decide whether to report the measured value.
     * @param ?string $method    Declaring method reported with the finding, when available.
     * @param ?string $class     Declaring class reported with the finding, when available.
     * @param ?int    $line      One-based source line used to locate the declaration or finding.
     * @param ?string $message   Optional explanation of the finding and its local evidence.
     */
    public function __construct(
        public string $rule,
        public string $file,
        public int $value,
        public int $threshold,
        public ?string $method = null,
        public ?string $class = null,
        public ?int $line = null,
        public ?string $message = null,
    ) {}

    /**
     * Preserve the public JSON representation of a violation.
     */
    public function toArray(): array
    {
        return [
            'rule'      => $this->rule, 'file' => $this->file, 'method' => $this->method,
            'class'     => $this->class, 'line' => $this->line, 'value' => $this->value,
            'threshold' => $this->threshold, 'message' => $this->message,
        ];
    }
}
