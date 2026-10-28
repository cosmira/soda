# Configuration

- [Create a configuration](#create-a-configuration)
- [Generate editable rules](#generate-editable-rules)
- [Paths](#paths)
- [Rules](#rules)
- [Exclusions](#exclusions)
- [Per-rule exclusions](#per-rule-exclusions)
- [Custom rules](#custom-rules)

Without a configuration file, Soda uses its standard rules when you pass a source
path. A project configuration saves the paths and rules for your daily check.
Choose one starting point:

- [Create a short configuration](#create-a-configuration) to keep the standard
  bundle and set paths or exclusions.
- [Generate editable rules](#generate-editable-rules) to tune thresholds or remove
  individual standard rules. Use this instead of first creating the short file.

Both approaches use the same `soda.php` format. `init` refuses to overwrite an
existing file.

## Create a configuration

Create `soda.php` in your project root:

```php
<?php

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with(RuleCatalog::standard());
```

Replace `src` with your source directory, then run:

```bash
vendor/bin/soda
```

This runs the standard bundle against the configured paths. Commit `soda.php`
so your team and [CI](CI.md) use the same check.

## Generate editable rules

To change individual thresholds or remove standard rules, generate the expanded
configuration instead of writing the short configuration above:

```bash
vendor/bin/soda init
```

The generated file lists each standard rule as a PHP object, including its
configured constructor arguments. Its initial path is `src/`; change that path
for your project. Edit a constructor argument to tune a threshold, or remove the
object to disable that rule. `init` refuses to replace an existing `soda.php`.

## Paths

Use `withPaths()` to choose the default source directories. Paths built with
`__DIR__` stay relative to the configuration file, even when you run Soda from
another directory.

Explicit command-line paths replace the configured paths for that run:

```bash
vendor/bin/soda quality src/ tests/
```

The selected configuration still supplies the rules and exclusions. To choose a
configuration explicitly, use:

```bash
vendor/bin/soda quality src/ --config=tools/soda.php
```

Without `--config`, Soda looks for an exactly named `soda.php` in each source
directory and its parents, up to ten directories including the starting one.
Without source arguments, that search starts in your current directory. If the
sources resolve to different configurations, select one with `--config`.

## Rules

A custom configuration starts with no rules. To run only selected checks, pass
rule objects to `with()`:

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Structure\MaxArguments;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new MaxArguments(5),
    ]);
```

This configuration runs only those two rules. It does not add them to the
standard bundle. Repeated `with()` calls append rules in order; they do not
replace an earlier rule with the same ID. To tune the standard bundle, edit the
expanded configuration produced by `init`.

Use `RuleCatalog::standard()` for the complete standard bundle, or
`RuleCatalog::checks('complexity')` for its complexity section. Both use the
arguments in the built-in catalog. Optional rules must be added explicitly.

The [rule reference](rules/README.md) lists constructor options and standard
membership. To inspect your installed version, run:

```bash
vendor/bin/soda list:rules
```

## Exclusions

Vendor directories are excluded automatically. Exclude generated code and
caches in your configuration:

```php
<?php

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/packages'])
    ->exclude([
        __DIR__.'/app/Generated',
        __DIR__.'/packages/Legacy',
        '*.blade.php',
    ])
    ->with(RuleCatalog::standard());
```

Configuration exclusions combine with repeated `--exclude` options:

```bash
vendor/bin/soda quality app/ --exclude=Generated --exclude=cache
```

Relative exclusions expand against each source root. Absolute exclusions apply
directly. A basename mask such as `*.blade.php` matches files at every depth.
Directory exclusions include their descendants without matching siblings with
similar names. Repeated `exclude()` calls add exclusions.

If exclusions remove every source file, Soda reports `No files found to scan`
and exits with status `1`.

## Per-rule exclusions

To exempt files from one rule while keeping the other checks, call `exclude()`
on that rule instance:

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxPublicMethods;

return Soda::configure()
    ->withPaths([__DIR__.'/src'])
    ->with([
        (new MaxPublicMethods(20))->exclude([__DIR__.'/src/Generated/*']),
    ]);
```

Repeated calls add exclusions. In per-rule masks, `*` can cross directory
separators. For example, `*/Data/*` matches a `Data` directory at any depth and
its descendants, but not a directory named `Metadata` or `DataExtra`.

These exclusions do not disable parsing, so parse errors remain visible. Project
and research checks receive filtered project facts after inheritance is resolved.
Excluding a parent class does not exempt children in other files or reduce their
inherited public method count.

## Custom rules

Custom rules extend `Cosmira\Soda\Contracts\Check`. Register their instances in
`with()` like built-in rules. See [Custom checks and rule sets](PLUGINS.md) for a
complete implementation.

For changes to an older configuration, see the [migration guide](MIGRATION.md).
