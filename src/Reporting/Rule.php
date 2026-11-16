<?php

declare(strict_types=1);

namespace Cosmira\Soda\Reporting;

use Attribute;

/** Presentation metadata kept beside the check that produces the finding. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Rule
{
    /**
     * The displayed default is a diagnostic threshold, not a constructor argument list.
     */
    public function __construct(
        /**
         * Heading displayed beside findings in the console report.
         */
        public string $label,
        /**
         * Remediation guidance shared by console and JSON reports.
         */
        public string $advice,
        /**
         * Presentation level: error or warning; all violations affect the quality result.
         */
        public string $severity = 'warning',
        /**
         * Historical diagnostic value displayed by list:rules, independent of constructor options.
         */
        public int|float $default = 0,
        /**
         * Direction for interpreting a diagnostic threshold: min, max, or unspecified.
         */
        public ?string $comparison = null,
        /**
         * Override for historical section names that differ from the class namespace.
         */
        public ?string $section = null,
    ) {}
}
