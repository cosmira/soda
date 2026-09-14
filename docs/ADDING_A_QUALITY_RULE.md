# Adding a quality rule

1. Create a final named class extending `Rules\Check` or `Rules\ExpressionCheck` in the relevant
   `src/Rules` area. Keep its constructor options, condition, violation metadata
   and any dedicated algorithm together.
2. Add one flat entry to `resources/catalog.php` with its class, default arguments,
   section, severity, default display value, label, advice and comparison direction.
3. Add a behavioral test: include a passing boundary, a failing boundary, actual
   measured values and locations. Use `Analysis\FactCollector` to prepare a real
   PHP fixture or construct `FileFacts`/`ProjectFacts` for an isolated check.

Internal array shapes are declared once in `Analysis\FileFacts`. Consult those
annotations for collected data and `resources/facts.php` for expression fields,
units, semantics and collectors.

The executor does not change when adding an ordinary rule. Catalog entries feed
standard rule sets, configuration generation and discovery. Optional rules can be registered
directly without joining the standard catalog.

Ensure `php soda list:rules` shows the rule id, section, severity, default, and label.
Verify that `init` creates the intended named class and that JSON schema 4 retains
the violation evidence. Update documentation when public behavior changes.

See [PHP rules and plugins](PLUGINS.md), [expression rules](RULE_EXPRESSIONS.md)
and [the execution flow](PROJECT_FLOW.md).

## Consistent rule implementation

Every built-in check is `final` and directly extends one of two bases:

- `Check`: implement `checkFile()` or `checkProject()`, decide what violates the
  rule, and construct `Violation` in that class. `requiredAnalyses()` returns `[]`
  for an AST-only check; `null` requests all metric collectors.
- `ExpressionCheck`: declare `expression()` and construct `Violation` in
  `violation()`. The base only compiles the expression, selects fact rows and
  runs the appropriate file/project stage. It also extends `Check`.

Do not add family-specific rule parents or use a rule ID to dispatch to its
algorithm elsewhere. A rule ID identifies diagnostics. Dedicated visitors and
algorithm helpers may remain beside their rule; they are internal collaborators,
not alternative check contracts.

For example, `Architecture/NoRuntimeHooks.php` contains the actual function names
it rejects. `Documentation/MultilineMethodPhpDoc.php` selects methods explicitly.
Changing either policy requires reading and changing that concrete rule.
