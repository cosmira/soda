# Soda

Soda checks PHP code for oversized methods, complex conditions, naming problems,
and structural issues. It reports what failed and where, with a non-zero exit
status when violations are found.

Requires PHP 8.3.19 or higher.

```bash
composer require --dev cosmira/soda
vendor/bin/soda quality src/
```

The first check needs no configuration: Soda uses its standard rules when it
finds no `soda.php`. Vendor directories are excluded automatically.

## Configure once, run daily

Create `soda.php` in your project root. Choose named classes and pass their options:

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Structure\MaxArguments;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new MaxArguments(5),
    ]);
```

Then run:

```bash
vendor/bin/soda
```

Only the listed rules run. Remove a rule to disable it. To use the whole standard
set, pass `RuleCatalog::standard()` to `with()`; import
`Cosmira\Soda\Config\RuleCatalog`. `vendor/bin/soda init` can generate an expanded,
editable configuration equivalent to `RuleCatalog::standard()`, including its
thresholds and readonly-data and boolean-method policies. Optional rules remain opt-in.

## Useful commands

| Command | Purpose |
| --- | --- |
| `vendor/bin/soda` | Check the paths in `soda.php` |
| `vendor/bin/soda quality src/ tests/` | Check explicit paths |
| `vendor/bin/soda --config=tools/soda.php` | Select a configuration |
| `vendor/bin/soda --report-json=quality.json` | Save a JSON report for CI |
| `vendor/bin/soda quality src/ --exclude=generated` | Exclude a directory; repeat for more exclusions |
| `vendor/bin/soda list:rules` | Inspect available rules and defaults |
| `vendor/bin/soda --help` | Show check options |

Use the same check command in CI. Exit status `0` means no violations; `1` means
a failed check or a configuration/input error. Soda complements a formatter and
PHPStan or Psalm by checking code structure and maintainability.

## Learn more

- [Configuration](docs/SODA_PHP_CONFIG.md): paths, thresholds and rule sets.
- [Rules and examples](docs/README.md): structure, complexity and naming.
- [Custom rules](docs/PLUGINS.md): implement a named PHP class.
- [Report JSON](docs/QUALITY_REPORT_JSON.md): machine-readable results.
- [Project flow](docs/PROJECT_FLOW.md): where parsing, checks and reporting happen.
- [Design philosophy](docs/DESIGN_PHILOSOPHY.md): concerns, fluent APIs and value objects with executable examples.
- [Research metrics](docs/RESEARCH_METRICS.md) and [100-finding corpus review](docs/research/CORPUS_REVIEW.md): optional cognitive complexity; cohesion remains measurement only.
- [Migration](docs/MIGRATION.md): changed namespaces and extension contracts.

Rule authors can inspect available facts with `vendor/bin/soda list:metrics`.
Expression syntax is an optional implementation tool inside a rule class.

## Development

Clone this repository and run `composer install`. Inside the Soda repository,
use `php soda` in place of `vendor/bin/soda`. Run `composer test` for behavioral
tests and `composer ci` for the full project checks.

Licensed under the [BSD 3-Clause License](LICENSE).
