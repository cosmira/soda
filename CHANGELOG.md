# Changelog

## 1.0.0 — Unreleased

First stable release of Soda under `cosmira/soda`.

- Count alternative branches at their owning if/try depth; preserve nested body levels.
- Scope unused-interface advice to the analysed project and explicit external contracts.

- Keep nested callable/class bodies outside assignment-in-condition analysis;
  continue checking their own conditions and outer call arguments.
- Accept immediately invoked literal callables as explicit targets and single
  queries used with `instanceof` or null fallback as simple conditions.
- Do not infer boolean parameters from truthiness, casts or comparisons of data;
  preserve checks for explicit boolean types and defaults.
- Preserve guarded forwarding of computed values in the ask-then-tell check;
  do not count creation of first-class callables as command execution.
- Preserve factory hooks backed by ancestor declarations or observed bounded
  same-object dispatch, including installed Composer dependencies.
- Count explicit PHPDoc declaration types and template bounds as interface consumers,
  without counting prose mentions or disconnected interface self-reference cycles.
- Read missing ancestor signatures from installed Composer PSR-4 dependency sources
  without executing the autoloader or dependency code.
- Resolve unused-input contracts across analysed files, preserving known inherited
  positions while still reporting extra implementation parameters.
- Preserve callback positions and registered native stream hook signatures; avoid
  treating consumed query values, internal state decisions and inline documentation
  examples as architectural violations or disabled code (18 findings in three more Laravel packages).
- Recognize `get_defined_vars()` as a lexical parameter read, eliminating 205 false
  unused-input findings in Laravel Prompts v0.3.21; clarify positional callback advice.
- Preserve array copies in `useless_variable` when alias elements or the source mutate.
- Allow exact external signatures in `MaxArguments::contracts` without relaxing other methods.
- Explain `mixed` for inherited untyped parameters in diagnostic advice.
- Configure checks with named PHP classes and constructor arguments in `soda.php`.
- Run the standard checks without configuration using `vendor/bin/soda quality src/`.
- Generate an equivalent standard configuration with `vendor/bin/soda init`.
- Inspect rules and metric definitions with `list:rules` and `list:metrics`.
- Extend `Cosmira\Soda\Rules\Check` for PHP checks or `ExpressionCheck` for
  expressions compiled by phplrt inside a named class.
- Parse each PHP file once and run file and project checks through one executor.
- Emit measured values, thresholds, source locations and advice in JSON schema 4.
- Strengthen factory, transparent-wrapper, repeated-condition and boolean-parameter checks
  against decorative metadata, local aliases and supported composition disguises.
- Add eleven standard checks for unused inputs/state, interface contracts and consumers,
  mode parameters, repeated type dispatch, delegation depth, module cycles, native parameter
  types and effective class size. See the [rule matrix](docs/STRICT_RULES_IMPLEMENTATION.md)
  for exact boundaries and validation.

Requires PHP 8.3.19 or later. PHP parsing uses nikic/php-parser.
The standard set contains 82 checks; three additional checks are opt-in.

Pre-release users must update the Composer package name, PHP namespace prefix
and internal imports. Breathing metrics and plugin classes have been removed.
See the [migration guide](docs/MIGRATION.md) for details.

Cohesion experiments are reserved for a later release and are not part of 1.0.
