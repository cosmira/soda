<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

final readonly class Violation
{
    /**
     * Carry a measured result and its source location without intermediate builders.
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
