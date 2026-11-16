# Migrating to the simplified rule runtime

## Package and vendor

The 1.0 package is `cosmira/soda`, hosted at
[cosmira/soda](https://github.com/cosmira/soda).
Replace the previous `bunnivo/soda` Composer requirement and change the PHP
namespace prefix from `Bunnivo\Soda\` to `Cosmira\Soda\` in configuration,
custom rules and application imports. Old namespace aliases are not provided.
Apply the namespace changes below when importing rules or extending Soda.

## Configuration and reports

The configuration remains a PHP file returning `Soda::configure()->withPaths(...)->with(...)`.
Keep rule class names, constructor settings and rule IDs; update their imports.
`Analyzer::file()`, `Analyzer::paths()`, `Analyzer::analyze()` and the fluent
`config(...)->analyze()` entry remain available. JSON output uses schema 4.
See [Report JSON](QUALITY_REPORT_JSON.md) for its fields and command exit status.

## Namespace changes

All names below are relative to `Cosmira\Soda\`.

| Previous location | New location |
| --- | --- |
| `Plugins\Rules\Structural\*` | `Rules\Structure\*` |
| `Plugins\Rules\ControlFlow\*`, `Plugins\Rules\ComplexControl\*` | `Rules\Complexity\*` |
| Other `Plugins\Rules\Complexity\*` | `Rules\Complexity\*` |
| `Plugins\Rules\Naming\*` | `Rules\Naming\*` |
| Architecture checks under `Plugins\Rules\Architecture\` | `Rules\Architecture\` |
| PHPDoc checks formerly under `Plugins\Rules\Structural\` | `Rules\Documentation\` |
| `Plugins\Rules\UselessVariableRule`, `NoUnusedMethods` | `Rules\Usage\` with the same class name |
| `Plugins\Rules\NumericArrayIndex\NoNumericArrayIndex` | `Rules\Usage\NoNumericArrayIndex` |
| `Plugins\Rules\NestedArrayAccess\NoNestedArrayAccess` | `Rules\Usage\NoNestedArrayAccess` |
| `Plugins\Rules\ListOnlyArray\OnlyListArraysAllowed` | `Rules\Usage\OnlyListArraysAllowed` |
| `Plugins\Rules\NoTrivialDelegatingClasses` | `Rules\Structure\NoTrivialDelegatingClasses` |
| `Quality\Analyzer` | `Analysis\Runner` |
| `Quality\QualityResult` | `Reporting\QualityResult` |
| `Quality\Report\Violation` and report formatters | `Reporting\` |
| `Quality\Config\*` | `Config\` |
| `Quality\ConfigException` | `Support\ConfigException` |
| `Quality\RuleCatalog\RuleCatalog` | `Config\RuleCatalog` |
| `Quality\Rules\RuleExpression` | `Support\RuleExpression` |
| Shared collectors under `Quality\Ast`, `Quality\Visitor`, `Quality\Flow` | `Analysis\` |
| Rule-specific scanners and algorithms | Beside their rule in `Rules\<category>\` |

Built-in checks are discovered under `src/Rules`; their `Reporting\Rule` attributes
supply presentation metadata. `resources/standard.php` selects standard instances. For example:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Documentation\MultilineMethodPhpDoc;
use Cosmira\Soda\Rules\Structure\MaxArguments;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(10),
        new MaxArguments(5),
        new MultilineMethodPhpDoc(['public', 'protected', 'private']),
    ]);
```

## Custom checks and bundles

Replace `Config\SodaRule` or `Quality\Rule\RuleChecker` implementations with
`Contracts\Check`. Keep `id()` and move file work to `checkFile(FileFacts)` or
cross-file work to `checkProject(ProjectFacts)`. Both stages default to an empty
result. `requiredAnalyses(): array` defaults to `[]`, requesting only shared
syntax and structural facts. Additional collector groups must be explicit.

Read `$file->source`, `$file->nodes` and `$file->metrics` directly. Never parse
again inside a check. Project checks use `$project->files`, containing metrics
without the source or AST. Inherited class surfaces are resolved before that
stage. Do not retain or mutate the shared AST.

Replace `ViolationBuilder`, `ViolationAt`, occurrence factories and location
containers with one readonly `Reporting\Violation`, using named arguments:

```php
new Violation(
    rule: $this->id(),
    file: $file->path,
    value: $actual,
    threshold: $this->maximum,
    method: $methodName,
    class: $className,
    line: $line,
    message: $message,
);
```

Read `$violation->file`, `->method`, `->class` and `->line` directly. `value` and
`threshold` remain integers; absent location/message fields remain `null`.

The universal `Config\Rule(id, expression)` is removed. Put the expression in
an appropriately named `ExpressionCheck` subclass, validate constructor arguments
and build violations from actual measurements. See the complete [PHP check](PLUGINS.md)
and [expression check](RULE_EXPRESSIONS.md) examples.

Plugin wrappers and `Config\SodaPlugin` have been removed. Rule sets are arrays:

| Previous API | Replacement |
| --- | --- |
| `with([new StandardPlugin])` | `with(RuleCatalog::standard())` |
| `plugin(StructuralPlugin::class)` | `with(RuleCatalog::checks('structural'))` |
| `plugin(ComplexityPlugin::class)` | `with(RuleCatalog::checks('complexity'))` |
| `plugin(NamingPlugin::class)` | `with(RuleCatalog::checks('naming'))` |
| Custom `SodaPlugin::checkers()` | Return the same array from a function or PHP file and pass it to `with()` |
| `$config->pluginCheckers()` | `$config->checks()` |

Import `Cosmira\Soda\Config\RuleCatalog` for catalog selection. Explicit named
rules in `with([new MaxCyclomaticComplexity(10), ...])` remain unchanged.

## Rule selection and policies

Use `RuleCatalog::standard()` for the current standard selection. An explicit
`with([...])` list runs only those checks; additions to the standard do not
silently change an existing explicit configuration. Run `list:rules` to inspect
current rules, constructor defaults and opt-in checks.

`NoObjectCloning` and `NoRedundantRethrow` are opt-in. Cognitive complexity and
empty-finally checks are included in the standard selection. See
[Practical Design](DESIGN_PHILOSOPHY.md) for policy tradeoffs and
[Research Metrics](RESEARCH_METRICS.md) for measurement limits.

The standard bundle shares its property limit with `MaxArguments` to recognize
final readonly data constructors with public promoted fields. Standalone
`MaxArguments(5)` remains strict. To select the data policy explicitly:

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;

$properties = new MaxPropertiesPerClass(10);

return Soda::configure()->with([
    new MaxArguments(5, $properties),
    $properties,
]);
```

Breathing metrics and their rules have been removed. Remove those checks and
expression fields from old configurations; no replacement metric is provided.
Layer-dominance rules classify explicit directory roles rather than inferred
business responsibilities. See [Structural Metrics](STRUCTURAL_METRICS.md) for
current configuration and boundaries.

## Rule discovery and standard configuration

`resources/catalog.php` has been removed. Built-in checks carry a `Reporting\Rule`
attribute and are discovered automatically under `src/Rules`. `RuleCatalog::definitions()`
returns presentation metadata, class names and standard membership; it no longer
returns an `arguments` array. Use configured instances from `RuleCatalog::standard()`
or instantiate a rule directly. `resources/standard.php` supplies the standard
configuration and the file copied by `soda init`.

Discovery lists rules by identifier. Existing standard execution order and settings
are unchanged. `max_variable_name_words` and `no_nested_array_access`, previously
available only through direct construction, now also appear in `list:rules` as opt-in.

## Explicit analysis requirements

`Check::requiredAnalyses()` now returns `array` and defaults to `[]`: no additional
collector groups are required. Empty overrides can be deleted. Keep nonempty
requirements explicit. Custom overrides returning `?array` must change their return
type to `array`; returning `null` to enable every collector is no longer supported.
The low-level `FactCollector::collect($file)` API still collects all facts when
called without a selector.
