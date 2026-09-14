# Third-party material

Soda's own code remains BSD-3-Clause. Adapted third-party material retains the
notice and terms below; no Java or Python runtime dependency is introduced.

## Aibolit test scenarios

Source: [cqfn/aibolit](https://github.com/cqfn/aibolit/tree/1de37051b74fc61ce9313f350487d5807db732db),
commit `1de37051b74fc61ce9313f350487d5807db732db`.
Copyright (c) 2019–2026 Aibolit. [Full MIT license](licenses/MIT-Aibolit.txt).

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
