<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins;

use Bunnivo\Soda\Config\SodaPlugin;
use Bunnivo\Soda\Quality\Rule\RuleChecker;

/**
 * All built-in Soda rules in one plugin.
 *
 * Equivalent to registering StructuralPlugin + ComplexityPlugin +
 * BreathingPlugin + NamingPlugin together.
 *
 * @example Explicit full setup in soda.php:
 *
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new StandardPlugin(),
 *           new MyCustomRule(),
 *       ]);
 * @example Start from scratch — only your rules:
 *
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new MyCustomRule(),
 *       ]);
 */
final class StandardPlugin implements SodaPlugin
{
    /**
     * @return list<RuleChecker>
     */
    #[\Override]
    public function checkers(): array
    {
        return array_merge(
            (new StructuralPlugin)->checkers(),
            (new ComplexityPlugin)->checkers(),
            (new BreathingPlugin)->checkers(),
            (new NamingPlugin)->checkers(),
        );
    }
}
