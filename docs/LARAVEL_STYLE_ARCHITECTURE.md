# Laravel-Style DX

Soda should feel familiar to Laravel developers: one config file, clear console commands, readable defaults, and no hidden compatibility paths.

See [Practical Design](DESIGN_PHILOSOPHY.md) for the design principles and executable examples behind the rules.

## Target Experience

```bash
php soda init
php soda quality
```

`init` creates `soda.php` in the project root:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        new MaxFileLoc(700),
        new MaxMethodLength(100),
    ]);
```

That file is the single source of truth. No JSON config. No secondary app config. No callable configurator.

## Public API

```php
Analyzer::file($path)->analyse();

Analyzer::paths([$fileA, $fileB])
    ->config($pathToSodaPhp)
    ->analyse();

Analyzer::analyze($paths, configPath: null);
```

## Boundaries

| Layer | Responsibility |
|-------|----------------|
| `soda.php` | User paths and rule objects |
| `SodaConfig` | Collect configured paths and rule instances |
| `QualityCommand` | Resolve CLI paths or `withPaths()` and print reports |
| `Analyzer` | Small facade for programmatic use |
| `Analysis\Runner` | Collect facts and execute file/project checks |
| `FactCollector` / `AstFacts` | Read and parse PHP once; collect requested facts |
| Rule classes | Express one user-facing quality constraint |

## Rules

Rules should be added as plain PHP objects:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new BooleanMethodPrefix(ignore: ['runningUnitTests']),
    ]);
```

A new rule should have:

1. A named `Check` or `ExpressionCheck` class in `src/Rules/<category>` with constructor arguments for its options.
2. A flat catalogue entry for defaults, `init` and `list:rules`.
3. Behavioral tests and documentation.

Keep any rule-specific algorithm beside its rule. A simple comparison needs no
second checker or adapter and no executor or emitter changes.

## Avoid

| Avoid | Reason |
|-------|--------|
| Extra config files | Users should not wonder where a rule came from. |
| Stringly threshold arrays | `new MaxFileLoc(700)` is clearer than nested config. |
| Compatibility aliases before 1.0 | They create two names for one idea. |
| Generated DSL classes | A plain returned object is easier to read and edit. |
| Hidden defaults in docs | Examples should be close to real generated config. |

See [PROJECT_FLOW.md](PROJECT_FLOW.md) for the current execution path and responsibility map.
