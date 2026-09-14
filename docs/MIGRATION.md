# Migrating to the simplified rule runtime

## Package and vendor

The 1.0 package is `cosmira/soda`, hosted at
[cosmira/soda](https://github.com/cosmira/soda).
Replace the previous `bunnivo/soda` Composer requirement and change the PHP
namespace prefix from `Bunnivo\Soda\` to `Cosmira\Soda\` in configuration,
custom rules and application imports. Old namespace aliases are not provided.
Then apply the internal namespace changes below where relevant.

## Configuration and reports

The configuration remains a PHP file returning `Soda::configure()->withPaths(...)->with(...)`.
Keep rule class names, constructor settings and rule IDs; update their imports.
`Analyzer::file()`, `Analyzer::paths()`, `Analyzer::analyze()` and the fluent
`config(...)->analyse()` entry remain available. JSON output remains schema 4.
This refactor changes extension contracts and namespaces; it does not provide
compatibility aliases for the removed internal runtime.

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
| `Quality\Analyser` | `Analysis\Runner` |
| `Quality\QualityResult` | `Reporting\QualityResult` |
| `Quality\Report\Violation` and report formatters | `Reporting\` |
| `Quality\Config\*`, `Quality\ConfigException` | `Config\` |
| `Quality\RuleCatalog\RuleCatalog` | `Config\RuleCatalog` |
| `Quality\Rules\RuleExpression` | `Config\RuleExpression` |
| Shared collectors under `Quality\Ast`, `Quality\Visitor`, `Quality\Flow` | `Analysis\` |
| Rule-specific scanners and algorithms | Beside their rule in `Rules\<category>\` |

The catalogue's class column in `resources/catalog.php` is authoritative for
individual built-in rules. For example:

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
`Rules\Check`. Keep `id()` and move file work to `checkFile(FileFacts)` or
cross-file work to `checkProject(ProjectFacts)`. Both stages default to an empty
result. `requiredAnalyses(): ?array` defaults to `null` (all collectors); an empty
array requests only the shared syntax and structural facts.

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

There is no `QualityEngine`, `QualityRuleAdapter`, `EvaluationContext`, or shared
active threshold table. Use `Analysis\Analyser::check($files, $config)` for an
already constructed config. `resources/catalog.php` contains flat entries with
`class`, `arguments` and discovery metadata. Adding a normal built-in rule needs
its class, catalogue entry and behavioral test; the executor does not change.

## Preserved policies

The 69 remaining standard catalogue entries retain their IDs, settings and opt-in status.
PHPDoc checks remain explicitly enabled. Existing metric boundary behavior,
including non-positive disabling where previously supported, is unchanged.
Numeric violation allowances are absent; docblock suppression is outside this work.

The standard bundle already treats final readonly data constructors according to
the property limit. A standalone `MaxArguments(5)` remains strict. To apply the
same data policy explicitly, share the configured property rule:

```php
$properties = new MaxPropertiesPerClass(10);

return Soda::configure()->with([
    new MaxArguments(5, $properties),
    $properties,
]);
```

The policy applies to the existing recognized data shape (final readonly class,
public promoted fields), not arbitrary constructors. The
project's self-quality configuration now explicitly uses this policy. The
standard bundle also retains its historical boolean-method prefix vocabulary;
standalone `BooleanMethodPrefix` retains its own defaults and constructor options.

Run `php soda list:metrics [scope]` to discover expression fields, units, semantics
and calculation methods. PHP parsing stays with `nikic/php-parser`; phplrt handles
the expression language. `sebastian/complexity` still supplies the established
complexity calculation and `phpunit/php-file-iterator` preserves path expansion.
The unused direct runtime dependencies `sebastian/lines-of-code` and
`sebastian/version` are removed; test tooling can still depend on them transitively.

## Breathing removed

Breathing has been removed by product decision, with no replacement metric yet.
Delete `BreathingPlugin` and these six classes from existing configuration files:
`MinCodeBreathingScore`, `MinVisualBreathingIndex`, `MinIdentifierReadabilityScore`,
`MinCodeOxygenLevel`, `MaxWeightedCognitiveDensity`, `MaxLogicalComplexityFactor`.
The `Rules\Readability` and `Analysis\Breathing` namespaces no longer exist.

Their rule IDs and the expression fields `cbs`, `vbi`, `irs`, `col`, `wcd`, and
`lcf` are no longer registered. Expressions using those fields fail with an
unknown-field configuration error. There is no silent alias or substitution.
Other rules, including cyclomatic complexity, nesting and naming, remain.

The standard bundle now constructs every rule once with its final settings.
PHPDoc checks inspect declarations and produce violations directly; the internal
`MultilinePhpDocNodeScanner`, `MultilinePhpDocOccurrence` and
`MultilinePhpDocLocation` are removed. Name-length checks likewise use their
visitor directly, without `NameLengthNodeScanner`.

## Layer dominance: explicit roles

`MaxLayerDominancePercentage` keeps its constructor, ID and numeric thresholds,
but its classification has been revised separately. It recognizes the documented
architectural suffixes on declared class names, requires multiple roles, and
ignores arbitrary inheritance as layer evidence. A family of rules with helper
classes is therefore not a violation. This intentional behavior change is covered
by dedicated tests; see [structural metrics](STRUCTURAL_METRICS.md).

## Configuration loading and daily CLI

`vendor/bin/soda` now runs `quality` by default. Use `vendor/bin/soda list` for
all commands, or retain the explicit `quality` subcommand in existing scripts.
`ConfigLoader::resolve($files, $explicitPath)` replaces
`ConfigResolver::resolveConfig(...)`; `ConfigLocator` and `ConfigResolver` are
removed. `load($path)` and `loadDefault()` remain available.

The CLI executes the selected config once and uses the same object for paths
and rules. With no CLI paths, discovery starts at the current directory; with
explicit paths, it starts at the discovered files. Conflicting implicit configs
still require `--config`. A selected config is not replaced by a second lookup.
Public `Analyzer` and `QualityAnalysisBuilder` entry points remain available.

## File collection

`FactCollector::collect($path, $required)` returns source, AST and collected
metrics in one `FileFacts`. `AstFacts::collect($nodes, $lineMap, $required)`
collects resolved AST facts. The intermediate `ParsedPhpFile` and public visitor
container `QualityAstPipeline` are removed, along with `ThrowingPhpFileParser`
and the unused tolerant `PhpFileParser` utility. Missing or invalid files still
produce the same `ParserException` and CLI diagnostics.

To instrument parsing, supply a `PhpParser\Parser` to `FactCollector`'s constructor.
No separate Soda parser interface or visitor-registration framework is needed.

## Declaration facts

Class and trait metadata now lives on `FileFacts::$metrics['classes'][$name]`,
alongside the metrics for that declaration. `ProjectFacts` uses the same rows.

| Removed top-level map | Field on the declaration row |
| --- | --- |
| `publicMethodNames` | `public_method_names` |
| `propertyKeys` | `property_keys` |
| `parentTypes` | `parent` (absent when no parent is declared) |
| `traitUses` | `trait_names` |
| `implementedInterfaces` | `interface_names` (present on classes) |
| `publicTraitAliases` | `public_trait_aliases` |
| `nonPublicTraitMethods` | `hidden_trait_methods` |

`classTypes` and `MetricsExtractor::resolveClassType()` are removed. Use the class
row's key for its name and `kind` to distinguish `class` from `trait`. The layer
rule still derives roles from class-name suffixes, never from a parent/interface.
The interface-parent graph remains in `interfaceParents`. This changes internal
fact access for custom checks; the expression fields, named rules, measurements
and JSON schema remain unchanged.

## Generated configuration

New `soda init` files now match `RuleCatalog::standard()` exactly. The previous
separate starting limits are removed: arguments 16 → 3, cyclomatic complexity
26 → 8, methods per class 21 → 40, control nesting 4 → 3. The generated config
shares `MaxPropertiesPerClass(5)` with `MaxArguments` for the readonly-data policy
and uses `BooleanMethodPrefix::standard()` for the standard prefix policy.
Existing `soda.php` files are not rewritten. All 69 standard rules retain their
catalog order; optional PHPDoc rules remain opt-in.

## Usage internals

`OnlyListArraysAllowed` runs `ListOnlyArrayCollectingVisitor` on the existing AST
and creates violations from its sorted, unique `lines()`. The intermediate
`ListOnlyArrayAnalyser`, `ListOnlyArrayFinding`, and `ListOnlyArrayIssue` are removed.
Strict/pragmatic behavior, messages and source positions remain unchanged.
The three single-predicate helpers `UselessVariableReferenceEscape`,
`UselessVariableSourceUnset`, and `UselessVariableClosureCapture` are also removed;
their conditions now live in `UselessVariableAnalyser` at the scope checks that
use them.

## Rule implementations

Built-in rules now directly extend `Check` or `ExpressionCheck`. The intermediate
`ArchitectureEscapeRule`, `NameLength`, and `MultilinePhpDoc` bases are removed.
Their named rules retain constructor arguments, IDs, thresholds and diagnostics.

Architecture checks inspect the resolved AST directly. The shared classification
classes `ArchitectureEscapeVisitor` and `ArchitectureDeclarationProbe`, the
`architectureEscapes` metric, and the `architecture` collector requirement are
removed. Custom checks using that internal category map should inspect
`FileFacts::$nodes` and return `[]` from `requiredAnalyses()`. No DSL fields changed.

The name-length checks now select their own AST nodes and compare their own
constructor bounds. `NameLengthCollectingVisitor` and `NameLengthOccurrence`
are removed. Variable variables, anonymous classes, namespace segments and
Laravel migration method exceptions retain their previous behavior.

## Catalog projections and Usage helpers

Use `RuleCatalog::definitions()` for metadata and section fields. `RuleSections`,
`sectionsOrdered()`, `defaultThresholds()`, `metadataMap()`, `metadataForRules()`
and `SodaInitFileEmitter::ruleIdentifiers()` are removed. These projections had
no production consumers after the init refactoring. `RuleCatalog::standard()`
and `checks($section)` retain their respective configured-standard and standalone
constructor policies.

`NumericArrayIndexAnalyser` and `NestedArrayAccessAnalyser` are removed; their named
checks inspect AST nodes directly. `UnusedMethodInFileInheritance` is folded into
`UnusedMethodProtectedContract`, whose ancestor walk now terminates on cyclic
inheritance. `UnusedMethodCalls` collects recognized calls in one traversal.
The `UselessVariableArrowCapture` and `UselessVariableObjectMutation` predicates
now live where their results are used in `UselessVariableAnalyser`. Rule options,
ordinary findings and diagnostic positions remain unchanged.


## Internal names and fact shapes

These renames follow the namespace move above; all paths in the table are relative
to `Cosmira\Soda\`. Public `Analyzer` and `QualityAnalysisBuilder` entry points are
unchanged. Update custom code that injected the internal executor or used collectors.

| Previous internal name | Current name |
| --- | --- |
| `Analysis\Analyser` | `Analysis\Runner` |
| `Analysis\QualityMetricsVisitor` | `Analysis\StructureVisitor` |
| `Analysis\MetricsExtractor` | `Analysis\ClassFactsExtractor` |
| `Analysis\NullableReturnVisitor` | `Analysis\FactVisitor` |
| `Analysis\MethodVisitorTrait` | `Analysis\CallableScopes` |
| `Analysis\EfferentCouplingTypeSink` | `Analysis\EfferentCouplingReferences` |
| `Rules\Naming\RedundantNamingVisitor` | `Rules\Naming\NamingVisitor` |
| `Rules\Naming\RedundantNamingMethodResultFactory` | `Rules\Naming\NamingMethodFacts` |
| `Rules\Naming\RedundantNamingPhpTypeLabel` | `Rules\Naming\PhpTypeName` |
| `Rules\Structure\TellDontAskAliasRegistry` | `Rules\Structure\TellDontAskScopes` |

`StructureVisitor::metrics()` replaces `metricsByFile()`;
`NamingVisitor::facts()` replaces `namingReferences()`.
`EfferentCouplingReferences` uses `recordType()`, `recordName()` and
`recordClassOperand()` in place of `ingestParsedTypeHint()`,
`registerReferencedClassName()` and `registerClassOperandFromExpression()`.
`className()` replaces `publicFqcnFromName()`; identifier handling is private.
`EfferentCouplingGraph::currentParent()` replaces `currentExtends()`.
`EfferentCouplingParsedTypeIngestor` is removed: recursive type handling belongs
to `EfferentCouplingReferences` itself.

The empty `Analysis\Exception` marker interface is removed. Catch the existing
`Analysis\ParserException` or `Config\ConfigException` as appropriate; both still
extend `RuntimeException`. Error messages and diagnostic handling are unchanged.

`FileFacts` now declares the internal PHPStan array aliases imported by producers
and project aggregation. This adds static checking without changing runtime
values, metric keys or JSON. Optional fields describe collection stages; the
expression adapter uses a value-type map and the compiler's fact catalog.


## Complexity and naming facts

`Analysis\ComplexityVisitor` replaces `EnumAwareComplexityVisitor`,
`EnumAwareComplexityQualifiedNameBuilder` and `EnumAwareComplexityCyclomaticRunner`.
It has no constructor options and always visits nested declarations, scoring each
callable independently. `complexity()` returns `array<string, positive-int>`;
there is no intermediate `ComplexityCollection` or `Complexity` record. The
Sebastian cyclomatic visitor still computes each score. Enum compatibility tests
remain permanent language coverage rather than instructions to delete coverage
when an upstream implementation changes.

`Rules\Naming\CompoundVariableNameOccurrence` exposes readonly `name`, `words`,
`line`, `class` and `method` properties instead of getters. `wordCount()` remains
the derived measurement. `MaxVariableNameWords` consumes the typed occurrence
list directly; custom fact producers must respect the `FileMetrics` shape.

`Rules\Structure\DirectoryRoles` replaces `LayerMixingDirectoryAggregator`.
Its summary field is `roleCounts`; `PLAIN_ROLE` replaces `PLAIN_TYPE`. These are
architectural roles inferred from name suffixes, not PHP types or parent classes.
The rule ID, settings, heuristic and existing diagnostic text are unchanged.
