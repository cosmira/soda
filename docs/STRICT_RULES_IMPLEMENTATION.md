# Strict rule implementation

Scope: strengthen existing checks with adversarial tests and add concrete prohibitions.
Mandatory policy configuration, CI ownership and a general incomplete-analysis failure mode
are outside this change. All eleven new checks belong to the standard catalog (82 checks).
Existing explicit `with([...])` configurations retain their chosen checks.

## Strengthened rules

| Rule | Rejected disguise | Preserved distinction |
| --- | --- | --- |
| `NoTrivialFactories` | Rename the method, make it static/private, add attributes, interfaces or unrelated members; forward through local aliases, returned construction results or named arguments. | Defaults, references, variadics, relative construction and actual data transformations have distinct contracts. Exact published entry points may be configured explicitly. |
| `NoTrivialDelegatingClasses` | Add an interface, attribute or constant; rename the forwarded operation; move it into traits or a known parent. | Actual behavior, conversion or constructor work is not transparent forwarding. Boundary classes/interfaces require explicit configured contracts. |
| `NoRepeatedCompoundConditions` | Repeat a predicate through adjacent snapshots, constant comparisons, inherited methods or traits. | Separate private slots and lexical constants stay separate. Aliases of one body count once. Calls, hooked reads and arbitrary computed locals are outside the fingerprint vocabulary. |
| `NoBooleanParameters` | Remove `bool`, use literal/default boolean types, or introduce an alias before a truth test. | Readonly-only initialization remains distinct from selecting behavior. `NoUntypedParameters` separately requires native types. |

## Added rules

| Rule | Enforced condition | Evidence and boundaries |
| --- | --- | --- |
| `NoUnusedParameters` | An input must be read before it is definitely overwritten. | Declaration and callable identity; branch joins, short-circuit operators, captures and reference outputs. Exact external signature contracts are configurable. |
| `NoUnusedPrivateState` | Private fields/constants need reads from the effective class. | Declaration plus consuming owner; known trait precedence, overrides and aliases. Write-only array elements and unrelated receivers do not count. Parent private slots retain their owner. |
| `NoEmptyLocalInterfaces` | An interface must declare or inherit a known method contract. | Cross-file parent resolution; constants/attributes grant no exemption. Required external marker protocols must be explicit. |
| `NoRedundantLocalInterfaces` | An interface needs an actual type consumer. | Implements/imports alone do not count. Signature and inheritance edges propagate from consumers; self-references and unused cycles do not invent use. |
| `NoModeParameters` | A parameter must not select distinct operation sequences through literal alternatives. | Switch, match and equality branches, defaults and input aliases. Selecting data for the same operation is distinct. |
| `MaxDelegationDepth` | Transparent forwarding paths must remain within the configured limit; transparent cycles always fail. | Composed methods, private/static receivers, argument aliases and returned call results. Unknown targets end the proved path. |
| `NoDependencyCycles` | Analysed namespace modules must have no dependency cycle. | Strongly connected components of resolved type edges. Exact namespaces by default; configured prefixes use the longest match. |
| `NoRepeatedTypeDispatch` | The same family of alternatives must not be dispatched in several methods. | Type/literal/enum families, receiver aliases, parent and trait bodies; one vote per implementation. Default minimum: 2 methods. |
| `NoUntypedParameters` | Every parameter requires a native type. | Includes interfaces and nested callables; explicit `mixed` is allowed and does not exempt boolean-use analysis. |
| `MaxEffectiveMethodsPerClass` | Count concrete method names after nested trait composition. | Overrides and diamonds deduplicate names; aliases of every visibility count. Default limit: 40. |
| `MaxEffectiveClassLength` | Include maintained trait source in class size. | Each trait source counts once, including overridden code; parent source remains owned by the parent. Default limit: 500 logical lines. |

These are structural checks with documented vocabularies. They do not establish semantic
identity of business decisions or prove arbitrary whole-program PHP behavior. Missing source
is not loaded implicitly. The examples and precise options are documented in
[Structural Metrics](STRUCTURAL_METRICS.md) and
[Complexity and Readability Metrics](COMPLEXITY_READABILITY_METRICS.md).

## Integration

- Compact composition and usage facts are collected during the existing parse; project storage
  retains scalar/array facts rather than ASTs.
- Standard catalog, rule discovery, generated configuration, JSON diagnostic metadata and
  adversarial challenge descriptions include the new rules.
- Demonstration expectations now include independently unused inputs, write-only state and
  decorative interfaces; the original escape remains separately checked in every iteration.
- The project's existing public `Analyzer::paths` and `Soda::configure` creation entry points
  have exact factory contract entries in its self-check configuration. No global threshold
  or rule has been weakened to accommodate the implementation.

## Verification

Completed on PHP 8.5.4:

- Non-performance suite: **954 tests, 9,371 assertions**, passing.
- Performance suite with a 256 MB memory limit: **3 tests, 9 assertions**, passing.
- Soda self-quality: **no issues**; existing thresholds remain unchanged.
- Full PHPStan analysis: **no errors**.
- Pint: passing.
- Rector dry run: no proposed changes.
- `git diff --check`: passing.

PHPStan and Rector ran with `--debug` because their parallel workers cannot bind a local
socket in the execution sandbox. This changes execution mode, not the selected checks.
Rector retains the repository's pre-existing warnings about deprecated/unregistered skips.
