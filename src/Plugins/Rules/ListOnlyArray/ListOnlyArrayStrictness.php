<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

use PhpParser\Node\Expr\ArrayDimFetch;

/**
 * How aggressively “list-only arrays” are enforced.
 */
enum ListOnlyArrayStrictness
{
    /**
     * Forbid chained {@see ArrayDimFetch} only (`$x[$a][$b]`, `$cfg['a']['b']`).
     * Single-level `$row['id']` and `array{...}` PHPDoc are allowed — matches most real codebases.
     */
    case Pragmatic;

    /**
     * Also forbid string-literal keys at any depth and `array{...}` shapes in docblocks (maximum discipline).
     */
    case Strict;
}
