# Practical PHP implementation

All 69 standard rules, constructor settings, configuration syntax and JSON schema 4 remain in place. This work changes two diagnostic algorithms and then removes redundant internal validation.

1. **Readable conditions:** implemented. Simple predicates/comparisons and homogeneous boolean chains remain inline; mixed logic and nested work have separate explanations. Assignment policy and boolean chain length remain independent. Tests cover renamed variables, multiline expressions, callable scope, every control-condition location, and full/selective collection parity.
2. **Project method usage:** next. Collect compact declarations and scoped calls during the existing parse, then resolve traits and inheritance after all configured files are known.
3. **Internal facts:** next. Trust collector shapes in the three control-flow rules and construct violations directly.

Corpus comparison uses the unchanged revisions in `research/corpus.json`. Run `php docs/research/practical-php-corpus.php "$PWD" /path/to/snapshots /path/to/output` against each version. The harness selects only the two changing rules, so unrelated historical policies cannot obscure their diagnostic changes. Upstream code is parsed, never executed. Counts do not measure human readability.

Conditions validation: self-quality 0; 44 focused tests / 252 assertions; full workspace suite 814 tests / 8416 assertions (including 56 concurrent, uncommitted research tests outside this PR); PHPStan, Pint and fresh Rector pass. Separate performance suite: 2 tests / 6 assertions, 1.056 s, 20 MiB PHPUnit peak. See [corpus diagnostic review](research/PRACTICAL_PHP_REVIEW.md).
