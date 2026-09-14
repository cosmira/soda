# Project flow

Daily use: `vendor/bin/soda` reads `soda.php` and checks its configured paths.
A first check without a config is `vendor/bin/soda quality src/`.
`init`, `list:rules`, and `list:metrics` are optional setup and reference commands.
Inside this repository, use `php soda` instead of `vendor/bin/soda`.

The CLI path is **QualityCommand → ConfigLoader → Runner → report**.
`ConfigLoader` finds and executes one config. The CLI reuses that same instance
for paths and checks, then calls the executor directly. It does not route through
the public PHP facade or reload the config after discovering files.

## Responsibilities

| Area | Responsibility |
| --- | --- |
| `Commands` | CLI paths, options, discovery and output |
| `Config` | Fluent configuration, loading, flat rule catalog, expression compilation |
| `Analysis` | PHP parsing, shared facts, class hierarchy and one executor |
| `Rules/{Structure,Complexity,Naming,Architecture,Usage,Documentation}` | Named checks and the algorithms specific to them |
| `Reporting` | One readonly violation, result and formatters |

`Analyzer` and `QualityAnalysisBuilder` remain the public PHP entry points. They call
`Analysis\Runner`. It loads configuration, reads the configured checks,
unions their analysis requirements, and processes each distinct input file once.

`FactCollector` reads the file and calls nikic's parser once. It computes the
logical line map and source-comment facts. `AstFacts::collect()` resolves names
and parents, runs only the required visitors and returns file, class and method
facts. Visitors are local to this operation and are not exposed through a
pipeline container. `SingleVisitorTraversal` supports rule-specific walks over
the same AST.

Every file check receives the same `FileFacts` instance: path, source, AST and
metrics. Dedicated checks can walk that AST but never parse the file again.
`FileFacts` declares the internal array shapes (`FileMetrics`, `ClassFacts`,
`MethodFacts`, and naming records) in PHPStan annotations. Collectors and
`ProjectFacts` import those definitions instead of maintaining competing shapes.
Optional fields describe collection stages and requested analyses; they are not
a second runtime representation. `ExpressionRow` describes the permitted value
types of the row adapter; the field names, scope and expression types are checked
against `resources/facts.php` by the compiler.

`ProjectFacts` stores the scalar and array data needed after file processing.
Each `classes[$name]` row contains the class or trait's measurements and declared
relationships together:

| Field | Meaning |
| --- | --- |
| `kind` | `class` or `trait`; interfaces have their own parent graph |
| `public_method_names`, `property_keys` | Declared members used to compute effective counts |
| `parent` | Resolved parent name when a class declares one |
| `trait_names` | Used traits |
| `interface_names` | Implemented interfaces, including an empty list on a class |
| `public_trait_aliases`, `hidden_trait_methods` | Visibility adaptations for composed methods |

Project resolution indexes those same declarations by name, expands inheritance
and traits, and updates the effective counts in the existing class rows. It no
longer builds eight separate metadata tables. The separate `interfaceParents`
graph describes interface inheritance; interfaces are not counted as class rows.
Repeated declarations retain the first available value for each field, preserving
the previous merge policy. The declaration's `kind` comes from syntax; architectural
roles are derived only by the layer rule from class-name suffixes.

The executor releases the current `FileFacts` before advancing and project checks
receive no source or AST. Parse errors become `parse_error` violations and remaining
files are still analysed. Do not retain AST nodes in a custom check.

`resources/facts.php` documents expression fields and collector requirements.
`resources/catalog.php` records classes, default arguments and report metadata.
The `default` catalog field describes defaults for discovery and `init`; execution
reads the configured rule instances, not a global table of thresholds.

`RuleCatalog::standard()` preserves two historical policies explicitly: the readonly data
constructor exemption associated with its property limit, and its original
boolean method prefix vocabulary. Standalone rule constructors retain their own
configuration behavior. No numeric violation allowance is introduced.

See [migration](MIGRATION.md) for namespace and extension changes. Existing
metric formulas and diagnostic evidence are the compatibility boundary. Internal
containers and execution adapters are deliberately not preserved.
