# Practical rules: implementation evidence

This tracks the full [accepted plan](PRAGMATIC_RULES_PLAN.md). An unchecked item is outstanding work, not an implicit reduction of scope.

- [x] 1. Publish the philosophy and executable examples of concerns, fluent builders, explicit copies, readonly data, value objects, functions, adapters and transparent forwarding. **Implemented:** `tests/fixtures/design/practical.php`, `PracticalDesignTest`, and forwarding counterexamples.
- [x] 2. Improve transparent-class detection and equivalent readonly constructor-state handling. **Implemented:** 57 targeted tests / 112 assertions; full suite 630 tests / 7,035 assertions, PHPStan, Pint, fresh Rector, zero-violation self-quality, and separate performance tests (2 / 6) passed on PHP 8.5.4. Review pending.
- [ ] 3. Improve advice and document compatibility decisions for cloning, guards/returns, directory names and arrays. Preserve explicit rule meanings; no unannounced standard-set changes.
- [ ] 4. Add opt-in redundant-rethrow and empty-finally checks with adapted fixtures and attribution.
- [ ] 5. Add research Cognitive Complexity and cohesion (components and TCC), explain contributions and unresolved relationships, expose provenance through the fact catalog.
- [ ] 6. Evaluate a fixed PHP corpus, inspect candidate findings and non-findings, retain held-out projects and publish actual sample sizes.
- [ ] 7. Decide standard-set eligibility from evidence and complete behavior, quality, type, formatting, refactoring and performance gates; publish reviewable PRs.

The catalog, configuration syntax, violation budget and runtime architecture remain unchanged in the first implementation PR. No overall quality score or new runtime dependency is planned.
