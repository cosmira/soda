> Historical assessment of the implementation before the named-check simplification.
> Current architecture and migration: [Project flow](PROJECT_FLOW.md), [Migration](MIGRATION.md).
> Earlier scores and API examples below do not describe acceptance of the current work.

# Naming Conventions

Soda follows Laravel-style naming principles: consistency over cleverness, names that read like clear English, and boring predictable patterns over inventive ones.

## Classes

- Use `PascalCase`.
- Name entities in singular form: `RuleDefinition`, `Violation`, `MethodMetricsData`.
- Prefer role-specific suffixes only when they add meaning: `PhpFileParser`, `QualityJsonReportFormatter`, `RuleCatalog`.
- Avoid generic suffixes such as `Manager`, `Service`, and `Helper` unless the class is an integration point where that role is explicit and useful.

## Methods

- Use `camelCase`.
- Prefer verb-first names: `analyse()`, `calculate()`, `collectViolations()`, `writeReportJson()`.
- Use boolean prefixes consistently:
  - `is...` for state.
  - `has...` for presence.
  - `can...` for capability.
  - `should...` for policy decisions.
- Prefer domain accessors or conversion methods over Java-style `get...` / `set...`: `ruleValue()`, `threshold()`, `toArray()`, `resolveConfig()`.
- Avoid vague methods such as `process()`, `handleData()`, `execute()`, `run()`, and noun-only methods such as `result()`.
- Fluent terminal methods should name what they return: `violations()`, `metrics()`, `returnsByMethod()`.

## Variables

- Use `camelCase`.
- Use singular names for one value and plural names for collections: `$file`, `$files`, `$violation`, `$violations`.
- Prefer domain names over placeholders: `$qualityResult`, `$analysisMetrics`, `$parsedFile`.
- Avoid `$data`, `$result`, `$temp`, `$item`, and `$obj` in public APIs and non-trivial methods.

## Examples

- `QualityAnalysisBuilder::run()` -> `QualityAnalysisBuilder::analyse()`: the method now names the domain action instead of a generic execution step.
- `RuleChecker::result()` -> `RuleChecker::violations()`: fluent checks now end with the collection they produce.
- `ReturnStatementsVisitor::result()` -> `ReturnStatementsVisitor::returnsByMethod()`: the output shape is explicit without a generic getter.
- `StructureVisitor::result()` -> `StructureVisitor::metrics()`: the method names the grouped metric map it returns.
- `ProjectMetricsFileGatherer::gather()` -> `ProjectMetricsFileGatherer::collect()`: collection work uses the Laravel-familiar verb.
- `StatsCalculator::compute()` -> `StatsCalculator::calculate()`: calculator classes should use the domain verb they perform.
- `MetricsState::inc()` -> `MetricsState::increment()`: avoid abbreviations in method names.
- `$result` -> `$fileMetrics`: local variables should describe the domain value they carry.
- `$breathingList` -> `$breathingMetrics`: collection variables should be plural and domain-specific.
- `SodaRule::contents()` stays as-is: short DSL helpers can be clearer than heavier verb-first alternatives.
- `Application::runningUnitTests()` stays as-is because Illuminate calls that framework hook by name.
- `$files` remains plural because it is a collection, while `$file` is used for a single path.
- Boolean methods such as `isPassing()` keep the `is...` prefix because they answer a state question.

These conventions are based on the naming style commonly used across Laravel by Taylor Otwell: clear nouns for objects, verb-first methods for behavior, and predictable boolean prefixes.
