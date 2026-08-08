<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins;

use Bunnivo\Soda\Config\SodaPlugin;
use Bunnivo\Soda\Quality\Rule\BooleanMethodPrefixChecker;
use Bunnivo\Soda\Quality\Rule\NameLengthChecker;
use Bunnivo\Soda\Quality\Rule\RedundantNamingChecker;

/**
 * Naming convention rules: redundant naming, boolean method prefixes.
 *
 * Covers: redundant names, boolean method prefixes, and name lengths.
 */
final class NamingPlugin implements SodaPlugin
{
    #[\Override]
    public function checkers(): array
    {
        return [
            new RedundantNamingChecker,
            new BooleanMethodPrefixChecker,
            new NameLengthChecker,
        ];
    }
}
