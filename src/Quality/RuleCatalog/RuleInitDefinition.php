<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleCatalog;

/**
 * Describes how one catalog rule is emitted into `soda.php`.
 */
final readonly class RuleInitDefinition
{
    /**
     * @param 'int'|'layer'|'none'|'range'|'visibility' $constructor
     */
    public function __construct(
        public string $class,
        public string $constructor,
        public int $min = 0,
    ) {}
}
