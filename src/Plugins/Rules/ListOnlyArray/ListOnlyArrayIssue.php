<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

/**
 * Why list-only array discipline was violated (one primary reason per line).
 *
 * @internal
 */
enum ListOnlyArrayIssue: string
{
    /** `$row['key']`, `$cfg["x"]` */
    case StringKey = 'string_key';

    /** `$a[$i]['j']` or `$x['a']['b']` — list arrays must not be indexed in depth */
    case NestedAccess = 'nested';

    /** `array{...}` / structured array in PHPDoc */
    case PhpDocShape = 'phpdoc_shape';
}
