# Changelog

## 1.2.1 - 2026-10-30

- Keep research candidates, unknown paths, and coverage out of console output.
  Export the full research report with the existing `--research-json` option.
- Preserve quality violations, exit status, and JSON report schemas.

## 1.2.0 - 2026-10-29

- Support `phpunit/php-file-iterator` 5, 6, and 7, and `sebastian/complexity`
  4, 5, and 6. Existing projects can install Soda alongside newer PHPUnit dependencies.
- Resolve dependencies for the actual PHP runtime instead of forcing PHP 8.2
  during development and CI. PHP 8.2 remains the minimum supported version.
- Require phplrt 4.2.4 or later and regenerate the rule parser for that runtime.
- Support PHPUnit 11.5, 12, and 13 and newer Infection releases for development.
- Reorganize the README and documentation around installation, configuration,
  rule lookup, and CI. Test the rule index and behavior-preserving code examples.
- Add a tested Vale writing profile and a separate documentation-style workflow.
  Validate Composer metadata and reject outdated direct runtime dependencies in CI.
- Read documentation examples with Windows and Unix line endings.

Existing `soda.php` configurations keep their selected rules. No new quality
rules or report schema changes are introduced in this release.

## 1.1.2 — 2026-10-22

- Fix a fatal `Undefined constant GLOB_BRACE` crash on PHP builds whose glob
  lacks brace expansion, such as musl-based images.
- Report findings as `json`, `junit` or `teamcity` with
  `soda quality --report-type=`; pass `--report-file=` to write the report to a
  file instead of stdout. TeamCity service messages and a JUnit XML file both
  surface every violation as a failed test in the TeamCity Tests tab. Exit
  status, human output and the JSON schema stay unchanged.

- Reduce false positives for inherited and trait parameter contracts, factory
  dispatch, documented type consumers, native hooks and observable writes.
- Preserve receiver identity, input snapshots and prior effects when suggesting
  early exits, and recognize shared catch fallbacks and terminal calls.
- Run Infection with its maximum worker setting locally and in CI.
- Fix Windows test fixtures, documentation lint and current Rector findings.

## 1.1.1 — 2026-10-15

- Accept fluent predicates, nested argument calls and natural while conditions.
  Require parentheses only for mixed logical families without explicit grouping.
- Report redundant else/elseif only after proven unconditional exits. Preserve
  meaningful mutually exclusive operations and earlier non-exiting branches.
- Limit `prefer_continue` to nested executable control flow; accept flat positive
  branches and keep callback scopes, complete exits and finalizers separate.
- Preserve return variables carrying a relevant local type assertion, and avoid
  changing observable bindings inside try/finally.
- Keep `max_return_statements` available as an explicit rule with its literal
  return count; remove it from the standard gate (now 108 checks).
- Correct documentation rewrites to preserve truthiness, preceding work and loop
  reevaluation. Add parsed regression cases and executable documentation examples.

Existing explicit configurations retain their selected rules. Rules and IDs stay
compatible; this release narrows findings without new options or dependencies.

## 1.1.0 — 2026-10-15

- Detect mandatory constructor dependency cycles within a module, with concrete
  type edges and source locations, while preserving optional construction breaks.
- Recognize repeated object setup returned from factories and suggest expressive
  construction or a fluent API on the owner, with evidence across consumers.
- Add five standard checks: `no_redundant_boolean_comparisons`,
  `no_redundant_boolean_returns`, `no_useless_return`, `no_useless_concat`, and
  `prefer_combined_conditions`. The standard configuration now contains 109 checks.
- Extend `no_unneeded_ternary` to partial boolean branches while preserving
  boolean conversion, short-circuiting and branch evaluation order.
- Support per-rule `exclude([...])` paths and masks, file masks in command
  exclusions, and masked package configuration boundaries.
- Preserve declared cleanup exception contracts and stable owner cache writes.
- Keep bodyless interface and abstract methods out of statement-list checks.
- Remove five single-use implementation classes without adding dependencies or
  increasing the project's class budget.
- Document rule boundaries, upstream tools and research references, with parsed
  snippet tests and cross-file architectural scenarios.

The standard configuration can report new findings after upgrading. Existing
explicit rule lists continue to run only the selected checks. PHP 8.2 remains
the minimum supported version.

## 1.0.1 — 2026-10-14

- Add fluent `exclude([...])` configuration for the `quality` command, combined
  with CLI exclusions for default and explicit analysis paths.
- Keep similarly named sibling directories when excluding a directory.
- Handle interface and abstract methods without bodies in
  `NoDeclarationsBeforeEarlyExit` and `PreferCombinedGuards` without crashing.

## 1.0.0 — 2026-10-14

First stable release of Soda, published as `cosmira/soda` under the MIT license.
Requires PHP 8.2 or higher.

- Check PHP code with a standard rule set and no initial configuration.
- Configure named checks and constructor options in one `soda.php` file;
  generate a starting configuration with `soda init`.
- Inspect rules, defaults and analysis facts through `list:rules` and `list:metrics`.
- Check size, complexity, naming, PHPDoc, parameter usage, private state,
  composition, dependencies and owner behavior.
- Preserve known inheritance, trait, callback and framework contracts when
  evaluating rule findings.
- Return actionable source locations, measured values and thresholds, with
  JSON schema 4 reports and nonzero exit status for failed checks.
- Extend Soda through named PHP checks, expression checks and reusable rule sets.
- Collect cognitive complexity and cohesion measurements with documented limits.
- Report experimental representation-churn findings separately from violations;
  research output does not fail the quality gate.
- Use PSR-4 autoloading and support Illuminate 11, 12 and 13 on compatible PHP versions.

## Migrating from development versions

Update the Composer package name from `bunnivo/soda` to `cosmira/soda` and the PHP
namespace prefix from `Bunnivo\Soda\` to `Cosmira\Soda\`. Plugin wrappers and
Breathing metrics have been removed. Existing explicit rule lists continue to
run only the selected checks. See the [migration guide](docs/MIGRATION.md) for
namespace and extension-contract changes.
