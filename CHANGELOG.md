# Changelog

## 1.0.0 — Unreleased

First stable release of Soda under `cosmira/soda`.

- Configure checks with named PHP classes and constructor arguments in `soda.php`.
- Run the standard checks without configuration using `vendor/bin/soda quality src/`.
- Generate an equivalent standard configuration with `vendor/bin/soda init`.
- Inspect rules and metric definitions with `list:rules` and `list:metrics`.
- Extend `Cosmira\Soda\Rules\Check` for PHP checks or `ExpressionCheck` for
  expressions compiled by phplrt inside a named class.
- Parse each PHP file once and run file and project checks through one executor.
- Emit measured values, thresholds, source locations and advice in JSON schema 4.

Requires PHP 8.3.19 or later. PHP parsing uses nikic/php-parser.
The standard set contains 69 checks; five additional checks are opt-in.

Pre-release users must update the Composer package name, PHP namespace prefix
and internal imports. Breathing metrics and plugin classes have been removed.
See the [migration guide](docs/MIGRATION.md) for details.

Cohesion experiments are reserved for a later release and are not part of 1.0.
