<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

/**
 * Entry point for the fluent soda.php configuration API.
 *
 * @example Minimal custom-rule config:
 *
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new MyCustomRule(),
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
    /**
     * Start a fluent Soda configuration.
     */
    public static function configure(): SodaConfig
    {
        return new SodaConfig;
    }
}
