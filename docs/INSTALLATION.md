# Installation

- [Requirements](#requirements)
- [Install Soda](#install-soda)
- [Run your first check](#run-your-first-check)
- [Review your first findings](#review-your-first-findings)
- [Save your configuration](#save-your-configuration)
- [Choose paths and configuration](#choose-paths-and-configuration)
- [Next steps](#next-steps)

## Requirements

Soda requires PHP 8.2 or later and Composer. Install it as a development dependency
in the project you want to check. It works with PHP projects without a framework
integration.

## Install Soda

From your project root, run:

```bash
composer require --dev cosmira/soda
```

## Run your first check

Pass your source directory to the `quality` command:

```bash
vendor/bin/soda quality src/
```

Replace `src/` with a directory in your project, such as `app/`.
When Soda finds no `soda.php`, it uses the standard rules. Vendor directories are
excluded automatically.

A passing check ends with `[OK] No issues`. When the check fails, the report
identifies findings by rule and source location. Use the
[rule reference](rules/README.md) to review the relevant examples before changing
your code.

The command exits with `0` when the check passes. It exits with `1` when it finds
violations or encounters a configuration or input error. An empty source selection
is an error.

## Review your first findings

A failed quality check reports each source location, its measured value, the
selected limit, and advice. For example, the shipping workflow in the
[README](../README.md#understand-a-finding) produces this excerpt when checked
with `MaxControlNesting(3)`:

```text
src/ShipOrders.php
  × Line 10 (ShipOrders::ship)
    Control structure nesting: 4 (max 3)
    ↳ Return early for exceptional paths and extract the nested happy-path behavior into a named method.
```

- `src/ShipOrders.php` is the file to review.
- `Line 10 (ShipOrders::ship)` locates the nested path inside the method.
- `4 (max 3)` compares the measured nesting depth with the selected limit.
- The final line suggests a direction for refactoring. Check that it preserves
  your application's behavior before applying it.

The rule ID is `max_control_nesting`. Its [reference page](rules/max_control_nesting.md)
explains what counts toward the depth and how to configure its limit. JSON
reports include this identifier in the `rule` field; the console uses a readable
label. Other configured rules may report additional findings for the same code.

Treat the first report as a review list:

1. Open the rule page and read its scope and exceptions.
2. Decide whether its policy fits the code being checked. A policy violation
   does not by itself prove incorrect behavior.
3. If it fits, refactor one finding and verify the behavior with your tests.
   If it does not, choose an explicit rule set or a justified per-rule exclusion
   in your [configuration](SODA_PHP_CONFIG.md).
4. Run the same check again before adding it to CI.

Research checks produce evidence for review without creating quality violations.
They do not fail the check. The rule reference distinguishes whether a check runs
by default from whether its findings affect the exit status. Use
[separate reports](QUALITY_REPORT_JSON.md#separate-research-json) when collecting
research evidence.

## Save your configuration

Choose one starting point for `soda.php`:

- To keep the standard bundle, use the [short configuration](SODA_PHP_CONFIG.md#create-a-configuration).
- To tune thresholds or remove individual rules, generate editable rule objects:

```bash
vendor/bin/soda init
```

The generated file starts with `src/` as its source path. Set its
`withPaths([...])` entries to your source directories. `init` refuses to replace
an existing `soda.php`; use it instead of creating the short file first.

With either configuration, run:

```bash
vendor/bin/soda
```

The `quality` command is the default, so this checks the paths from your
configuration. See [Configuration](SODA_PHP_CONFIG.md) for exclusions and rule
selection.

## Choose paths and configuration

Explicit paths select the code for a particular run:

```bash
vendor/bin/soda quality src/ tests/
```

To select a configuration explicitly, pass its path:

```bash
vendor/bin/soda quality src/ --config=tools/soda.php
```

Without `--config`, Soda searches the source paths and their parents for
`soda.php`. If the selected sources resolve to different configurations, choose
one with `--config`.

To inspect available checks and command options, run:

```bash
vendor/bin/soda list:rules
vendor/bin/soda quality --help
```

## Next steps

- [Configuration](SODA_PHP_CONFIG.md) sets the rules for daily use.
- [Continuous integration](CI.md) runs your check on every change.
- [Rule reference](rules/README.md) explains findings and their options.

If you are developing Soda itself, use `php soda` instead of `vendor/bin/soda`.
