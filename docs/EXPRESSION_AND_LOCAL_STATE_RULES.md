# Expressions and local state

These checks use PHP syntax and lexical ownership. They do not infer runtime
values, alias propagation, or application-wide meanings of strings.

| Concern | Rule |
| --- | --- |
| Boolean conversion and short-circuit decisions | [no_unneeded_ternary](rules/no_unneeded_ternary.md) |
| Returns that override pending outcomes | [no_return_in_finally](rules/no_return_in_finally.md) |
| Rebinding an input parameter | [no_reassigned_parameters](rules/no_reassigned_parameters.md) |
| Long runs of local preparation | [max_consecutive_local_assignments](rules/max_consecutive_local_assignments.md) |
| Repeated expression literals | [no_repeated_string_literals](rules/no_repeated_string_literals.md) |

Use this overview to choose between policies for expressions and local state.
The linked rule pages are the primary reference for options and analysis
boundaries. These checks report violations when their selected policies are
broken; they are not nongating research checks.

## Configuration

```php
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxConsecutiveLocalAssignments;
use Cosmira\Soda\Rules\Complexity\NoRepeatedStringLiterals;
use Cosmira\Soda\Rules\Usage\NoReassignedParameters;

// Replace defaults when adding project-specific exceptions.
$rules = array_filter(RuleCatalog::standard(), static fn ($rule): bool => ! in_array(
    $rule->id(),
    ['no_reassigned_parameters', 'max_consecutive_local_assignments', 'no_repeated_string_literals'],
    true,
));

return Soda::configure()->withPaths([__DIR__.'/src'])->with([
    ...$rules,
    new NoReassignedParameters(ignoredParameters: ['cursor']),
    new MaxConsecutiveLocalAssignments(8),
    new NoRepeatedStringLiterals(3, minLength: 3, ignoredStrings: ['application/json']),
]);
```

Exceptions are exact and case-sensitive. Parameter names omit the `$` prefix.
All five checks run in `RuleCatalog::standard()` and generated `soda init` configs.
Existing explicit rule lists retain their selected checks. Tune policies with
project judgment: local input normalization,
straight-line data preparation, and repeated framework keys can all be useful.

## NoUnneededTernary

Reports full ternaries that restate a boolean conversion or short-circuit decision. Read [examples, options, and scope](rules/no_unneeded_ternary.md).

## NoReturnInFinally

Forbids `return` statements executed inside `finally` blocks. Read [examples, options, and scope](rules/no_return_in_finally.md).

## NoReassignedParameters

Rejects direct rebinding of by-value input parameters. Read [examples, options, and scope](rules/no_reassigned_parameters.md).

## MaxConsecutiveLocalAssignments

Limits the number of consecutive first assignments to new local variables at the start of a callable body. Read [examples, options, and scope](rules/max_consecutive_local_assignments.md).

## NoRepeatedStringLiterals

Reports string literals repeated several times as expressions within one class-like declaration. Read [examples, options, and scope](rules/no_repeated_string_literals.md).

## Verification evidence

The five rule suites and `ExpressionAndLocalStateIntegrationTest` cover default
enforcement, positive findings and legitimate counterexamples,
inclusive and triggering thresholds, invalid configuration, lexical ownership,
diagnostic evidence, catalog/init selection, JSON reporting and executable
configuration examples. Runtime cases verify boolean conversion with 16 different
PHP values and demonstrate how finally returns replace results and suppress
exceptions.

Dynamic aliases, runtime dispatch and shared string meaning are outside these
syntax checks; review the scope descriptions before adoption.
