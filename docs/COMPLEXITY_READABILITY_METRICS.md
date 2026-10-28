# Conditions and control flow

Use this overview to choose between checks for branches, nesting, method size,
and repeated decisions. Each linked rule page is the primary reference for
examples, options, and analysis boundaries.

- [Decision count](#max_cyclomatic_complexity) measures independent paths.
- [Nesting](#max_control_nesting) measures depth.
- [Cognitive complexity](#max_cognitive_complexity) weights control-flow patterns.
- [Statement count](#max_statements_per_callable) measures executable size.
- [Expressions and local state](EXPRESSION_AND_LOCAL_STATE_RULES.md) covers
  assignments, parameter binding, and repeated literals.

Choose a check for the problem you want to expose. Guard clauses can reduce
nesting without reducing the number of decisions. Extracting a named step may
help reading, but a lower metric is not proof of better behavior. Every proposed
refactoring must retain result values, side effects, and evaluation order.

Cognitive complexity is a quality rule derived from a research measurement: its
violations can fail the check. This differs from a research check such as
`representation_churn`, which emits evidence separately and never fails it.
See [rule selection](SODA_PHP_CONFIG.md#rules) and the [rule index](rules/README.md).

### no_useless_return

Reports a bare `return;` at the end of a function, method or closure. Read [examples, options, and analysis boundaries](rules/no_useless_return.md).

### no_redundant_boolean_returns

Reports `if` branches that only return opposite boolean literals. Read [examples, options, and analysis boundaries](rules/no_redundant_boolean_returns.md).

### no_redundant_boolean_comparisons

Reports comparisons of an already boolean expression with `true` or `false`. Read [examples, options, and analysis boundaries](rules/no_redundant_boolean_comparisons.md).

### no_useless_concat

Reports same-line concatenations whose two operands are string literals. Read [examples, options, and analysis boundaries](rules/no_useless_concat.md).

### prefer_combined_conditions

Reports two simple nested conditions that guard the same block. Read [examples, options, and analysis boundaries](rules/prefer_combined_conditions.md).

### max_cyclomatic_complexity

Limits the number of independent branches a single method may contain. Read [examples, options, and analysis boundaries](rules/max_cyclomatic_complexity.md).

## Nesting depth

### max_control_nesting

Limits how deeply control structures may nest inside a method. Read [examples, options, and analysis boundaries](rules/max_control_nesting.md).

### max_try_catch_blocks (excessive exception handling)

Limits how many `try` blocks a single method may contain. Read [examples, options, and analysis boundaries](rules/max_try_catch_blocks.md).

### max_return_statements

Limits the number of `return` statements a single method may contain. Read [examples, options, and analysis boundaries](rules/max_return_statements.md).

### max_boolean_conditions

Limits the number of boolean operands a single condition inside a method may combine. Read [examples, options, and analysis boundaries](rules/max_boolean_conditions.md).

## Exception flow policies

Check the [rule index](rules/README.md) for selection status. A rule appearing
in this overview is not necessarily enabled by default.

### no_redundant_rethrow

Reviews catch blocks whose only executable statement is rethrowing their bound exception. Read [examples, options, and analysis boundaries](rules/no_redundant_rethrow.md).

### no_empty_finally_blocks

Reports `finally` blocks that contain no executable statements. Read [examples, options, and analysis boundaries](rules/no_empty_finally_blocks.md).

### max_cognitive_complexity

Caps Soda's PHP cognitive complexity score per method, reporting every contributing line as evidence. Read [examples, options, and analysis boundaries](rules/max_cognitive_complexity.md).

### no_repeated_compound_conditions

Reports the same compound condition repeated across several methods of a class. Read [examples, options, and analysis boundaries](rules/no_repeated_compound_conditions.md).

### no_identical_branches

Reports full ternary branches and match arms that produce the same expression. Read [examples, options, and analysis boundaries](rules/no_identical_branches.md).

### no_nested_ternary

Reports a full ternary used directly as a result branch of another full ternary. Read [examples, options, and analysis boundaries](rules/no_nested_ternary.md).

### max_statements_per_callable

Limits how many executable statements a single method, function, closure or arrow function may contain. Read [examples, options, and analysis boundaries](rules/max_statements_per_callable.md).

### no_assignment_in_return

Reports hidden assignments inside return expressions. Read [examples, options, and analysis boundaries](rules/no_assignment_in_return.md).

### no_chained_assignments

Forbids chained assignments such as `$a = $b = calculate();`. Read [examples, options, and analysis boundaries](rules/no_chained_assignments.md).

### no_unneeded_ternary

Reports full ternaries that restate a boolean conversion or short-circuit decision. Read [examples, options, and analysis boundaries](rules/no_unneeded_ternary.md).

### no_return_in_finally

Forbids `return` statements executed inside `finally` blocks. Read [examples, options, and analysis boundaries](rules/no_return_in_finally.md).

### max_consecutive_local_assignments

Limits the number of consecutive first assignments to new local variables at the start of a callable body. Read [examples, options, and analysis boundaries](rules/max_consecutive_local_assignments.md).

### no_repeated_string_literals

Reports string literals repeated several times as expressions within one class-like declaration. Read [examples, options, and analysis boundaries](rules/no_repeated_string_literals.md).

### no_declarations_before_early_exit

Reports declarations before a guard clause when only the code behind the guard reads them. Read [examples, options, and analysis boundaries](rules/no_declarations_before_early_exit.md).

### prefer_combined_guards

Reports consecutive guard clauses that exit with the same statement. Read [examples, options, and analysis boundaries](rules/prefer_combined_guards.md).

### prefer_continue

Reports trailing conditions that add a level around nested control flow. Read [examples, options, and analysis boundaries](rules/prefer_continue.md).

### prefer_null_coalesce_assignment

Reports fallback assignments that repeat the target on both sides. Read [examples, options, and analysis boundaries](rules/prefer_null_coalesce_assignment.md).

## Third-party material

Soda's own code is licensed under MIT. Adapted third-party material retains the
notice and terms below; no Java or Python runtime dependency is introduced.

### Aibolit test scenarios

Source: [cqfn/aibolit](https://github.com/cqfn/aibolit/tree/1de37051b74fc61ce9313f350487d5807db732db),
commit `1de37051b74fc61ce9313f350487d5807db732db`.
Copyright (c) 2019–2026 Aibolit. [Full MIT license](#aibolit-license).

Destination: `tests/unit/ExceptionChecksTest.php`. Its file notice covers the
adapted scenarios. Source files inspected and adapted:

- `test/patterns/empty_rethrow/Simple.java`: direct bound-variable rethrow.
- `test/patterns/empty_rethrow/CatchWithFunctions.java`: statements before throw.
- `test/patterns/empty_finally/Empty.java`: finally with no statements.
- `test/patterns/empty_finally/NoFinally.java`: catch without finally.
- `test/patterns/empty_finally/NotEmpty.java`: an actual cleanup statement.

Java declarations and IO calls were replaced with minimal PHP syntax. Logging or
other work before rethrow is deliberately **negative** in Soda, unlike Aibolit's
broader EmptyRethrow pattern. Additional sibling catch, union, scope, comment,
trait, anonymous-class and nested-block cases specify Soda's PHP contract.
Production detectors are independently implemented against nikic's AST.

No third-party application fixture (including DBeaver's Apache-licensed files)
was copied. The donors' full test suites were not used as an oracle or executed
as part of Soda's tests.

#### Aibolit license

```text
(The MIT License)

Copyright (c) 2019-2026 Aibolit

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the 'Software'), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### Research definitions and source inspection

The cognitive and cohesion calculators and their PHP tests are independently
implemented. Sonar's specification, Aibolit and jPeek informed the comparison and
boundary cases; no Sonar implementation or donor graph code was copied. Pinned
links, formula differences and limitations are recorded in
[Research Metrics](RESEARCH_METRICS.md).

The [corpus manifest](research/corpus.json) pins read-only source snapshots.
Review records contain measurements, original commentary and source links, not
copies of upstream application files. No external corpus dependency is loaded
into Soda's runtime or distributed as an application fixture.
