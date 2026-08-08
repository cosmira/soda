<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Config;

/**
 * Entry point for the fluent soda.php configuration API.
 *
 * @example Minimal plugin-based config:
 *
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new MyCustomRule(),
 *           new MyCustomPlugin(),
 *       ]);
 * @example Config with explicit thresholds:
 *
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new MaxMethodLength(80),
 *           new MaxClassLength(400),
 *           new UselessVariableRule(),
 *       ]);
 */
final class Soda
{
    public static function configure(): SodaConfig
    {
        return new SodaConfig;
    }
}
