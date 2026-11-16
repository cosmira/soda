# Adding a quality rule

1. Create a final named class extending `Contracts\Check` or `Rules\ExpressionCheck` in the relevant
   `src/Rules` area. Keep its constructor options, condition, violation metadata
   and any dedicated algorithm together.
2. Add a `#[Rule(...)]` attribute from `Reporting\Rule` to the class with its label,
   advice and any non-default severity, diagnostic default or comparison direction.
   Concrete checks in `src/Rules` are discovered automatically; section names come
   from their namespace (`Complexity`, `Naming`, `Laravel`, otherwise `structural`);
   `section` on the attribute preserves historical exceptions.
   To enable the rule by default, add its instance to `resources/standard.php`.
3. Add a behavioral test: include a passing boundary, a failing boundary, actual
   measured values and locations. Use `Analysis\FactCollector` to prepare a real
   PHP fixture or construct `FileFacts`/`ProjectFacts` for an isolated check.

Internal array shapes are declared once in `Analysis\FileFacts`. Consult those
annotations for collected data and `resources/facts.php` for expression fields,
units, semantics and collectors.

The executor does not change when adding an ordinary rule. Discovery reads class
attributes without constructing optional rules. Keep `id()` independent of constructor state. `resources/standard.php` is the explicit
standard configuration and is copied verbatim by `soda init`. Optional checks stay
out of that file and can be registered directly.

Ensure `php soda list:rules` shows the rule id, section, severity, default, and label.
Verify that `init` creates the intended named class and that JSON schema 4 retains
the violation evidence. Update documentation when public behavior changes.

See [PHP rules and plugins](PLUGINS.md), [expression rules](RULE_EXPRESSIONS.md)
and [the execution flow](PROJECT_FLOW.md).

## Consistent rule implementation

Every built-in check is `final` and directly extends one of two bases:

- `Check`: implement `checkFile()` or `checkProject()`, decide what violates the
  rule, and construct `Violation` in that class. `requiredAnalyses()` defaults to `[]`
  for an AST-only check; override it only to list additional collector groups.
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

## Document the rule

Add a page under `docs/rules/` and link it from the [rule reference](rules/README.md).
Keep its identifier, default arguments, severity, and standard membership aligned
with its `Rule` attribute and `resources/standard.php`. Use this structure:

````markdown
# <rule-id>

<Describe the finding in one sentence. State whether the standard bundle enables it.>

## Why

<Explain the problem this rule detects.>

## Examples

```php
// Violating code
<example>

// Passing code
<example>
```

## Options

<Constructor arguments and defaults. Omit this section if there are none.>

## Configuration

```php
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\<Section>\<Rule>;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([new <Rule>()]);
```

## Related rules

- [<rule-id>](<rule-id>.md). <Explain the relationship.>
````
