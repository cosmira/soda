# Practical rules: implementation evidence

This tracks the full [accepted plan](PRAGMATIC_RULES_PLAN.md). An unchecked item is outstanding work, not an implicit reduction of scope.

- [x] 1. Publish the philosophy and executable examples of concerns, fluent builders, explicit copies, readonly data, value objects, functions, adapters and transparent forwarding. **Implemented:** `tests/fixtures/design/practical.php`, `PracticalDesignTest`, and forwarding counterexamples.
- [x] 2. Improve transparent-class detection and equivalent readonly constructor-state handling. **Implemented:** 57 targeted tests / 112 assertions; full suite 630 tests / 7,035 assertions, PHPStan, Pint, fresh Rector, zero-violation self-quality, and separate performance tests (2 / 6) passed on PHP 8.5.4. Merged as PR #2.
- [x] 3. Improve advice and document compatibility decisions for cloning, guards/returns, directory names and arrays. Criteria and standard selection preserved; see `POLICY_COMPATIBILITY.md`. Full suite: 635 tests / 7,040 assertions; PHPStan, Pint, fresh Rector and self-quality passed.
- [x] 4. Add opt-in redundant-rethrow and empty-finally checks with adapted fixtures and attribution. 25 focused tests / 115 assertions; full suite 660 / 7,213, PHPStan, Pint, fresh Rector, self-quality and separate performance tests (2 / 6, 1.783 seconds) passed.
- [x] 5. Implement research Cognitive Complexity and cohesion (integer components and direct public-field TCC), with line contributions, explicit unknowns and fact-catalog provenance. Full/requested parity, cross-file traits/inheritance, one parse per file and collectible ASTs are covered. See `RESEARCH_METRICS.md`.
- [x] 6. Evaluate 2,219 files / 19,888 callables / 1,940 types in five pinned repositories. Inspect 100 candidate findings and 25 non-findings, including 30/10 held-out examples. Preserve the discovery correction and every source-linked judgment in `research/CORPUS_REVIEW.md` and adjacent JSON. This is one assistant inspection, not independent empirical validation.
- [x] 7. Keep cognitive complexity and exception checks opt-in; reject the disconnected-groups built-in rule, retain measurement and its reproducible research-only candidate. Preserve all 69 standard checks and zero-violation policy. Local validation: 721 behavioral tests, PHPStan, Pint, fresh Rector and self-quality passed; final catalog invariants also passed separately. Separate performance gate: 2 tests / 6 assertions, 1.088 seconds at a 256 MiB limit. The research changes are prepared as the final reviewable PR; CI status is reported on that PR.

The catalog, configuration syntax, violation budget and runtime architecture remain unchanged in the first implementation PR. No overall quality score or new runtime dependency is planned.

Review: [contracts and examples, PR #2](https://github.com/cosmira/soda/pull/2); CI passed on PHP 8.3/8.4/8.5.

Recommendations: [PR #3](https://github.com/cosmira/soda/pull/3), CI passed on PHP 8.3/8.4/8.5.

Exception checks: [PR #4](https://github.com/cosmira/soda/pull/4), CI passed on PHP 8.3/8.4/8.5.

No donor implementation is adopted as an oracle. The new cognitive variant and cohesion formulas have explicit differences; no external runtime dependency, overall score, trait/VO quota, or second Runner is added.
