<?php

declare(strict_types=1);

/**
 * Copy to project root as `soda.php` or run `php soda init`.
 *
 * Two styles are supported:
 *
 * ─── New (recommended) ───────────────────────────────────────────────────────
 *   return Soda::configure()
 *       ->withPlugins([new MyRule()]);
 *
 * ─── Classic callable ────────────────────────────────────────────────────────
 *   return function (SodaConfig $config): void {
 *       $config->structural()->maxMethodLength(80);
 *   };
 *
 * @see docs/SODA_PHP_CONFIG.md
 * @see docs/ADDING_A_QUALITY_RULE.md
 */

use Bunnivo\Soda\Config\Soda;

$soda = Soda::configure();

$soda->structural()
    ->maxMethodLength(100)
    ->maxClassLength(800)
    ->maxArguments(3);

$soda->complexity()
    ->maxCyclomaticComplexity(15)
    ->maxControlNesting(3);

$soda->breathing()
    ->minCodeBreathingScore(25);

// Tighten in CI
if (($_ENV['CI'] ?? '') === 'true') {
    $soda->complexity()->maxCyclomaticComplexity(10);
}

return $soda->withPlugins([
    // Add your rules or plugins here:
    // new UselessVariableRule(),
    // new MyPlugin(),
]);
