# Practical PHP implementation

All 69 standard rules, constructor settings, configuration syntax and JSON schema 4 remain in place. This work changes two diagnostic algorithms and then removes redundant internal validation.

1. **Readable conditions:** implemented. Simple predicates/comparisons and homogeneous boolean chains remain inline; mixed logic and nested work have separate explanations. Assignment policy and boolean chain length remain independent. Tests cover renamed variables, multiline expressions, callable scope, every control-condition location, and full/selective collection parity.
2. **Project method usage:** implemented. Compact declarations and scoped calls are collected during the existing parse; traits, aliases and inherited/private scopes resolve after all configured files are known. Tests cover dynamic uncertainty, namespaces, callbacks, qualified parameter receivers, invalid/cyclic composition, AST release and full/selective parity. The old same-file policy helpers were removed.
3. **Internal facts:** implemented. Collector-owned PHPStan shapes flow into `FileFacts`; the three control-flow rules construct violations directly without per-row type/presence checks or intermediate violation lists. External configuration validation is unchanged.

Corpus comparison uses the unchanged revisions in `research/corpus.json`. Run `php docs/research/practical-php-corpus.php "$PWD" /path/to/snapshots /path/to/output` against each version. The harness selects only the two changing rules, so unrelated historical policies cannot obscure their diagnostic changes. Upstream code is parsed, never executed. Counts do not measure human readability.

Conditions validation: self-quality 0; 44 focused tests / 252 assertions; full workspace suite 814 tests / 8416 assertions (including 56 concurrent, uncommitted research tests outside this PR); PHPStan, Pint and fresh Rector pass. Separate performance suite: 2 tests / 6 assertions, 1.056 s, 20 MiB PHPUnit peak. See [corpus diagnostic review](research/PRACTICAL_PHP_REVIEW.md).

Project usage validation: 119 focused tests / 345 assertions on PHP 8.5; all 65 project-usage cases / 226 assertions on PHP 8.3; full workspace suite 864 tests / 8534 assertions (same 56 concurrent research tests excluded from this PR); self-quality 0, PHPStan, Pint and fresh Rector pass. Separate performance: 3 tests / 9 assertions, 1.465 s, 20 MiB PHPUnit peak, including 600 files containing 300 owner/concern pairs. The project AST-release test uses a weak reference and scalar-only traversal.

A final visibility regression covers `as final` with and without a new alias: adding finality must retain the source method visibility.

Internal-facts validation: full workspace suite 864 tests / 8534 assertions; self-quality 0; PHPStan, Pint and fresh Rector pass. Separate performance: 3 tests / 9 assertions, 1.451 s, 20 MiB PHPUnit peak. All 147 diagnostic objects on 148 test sources/provider-generated snippets exactly match the second step, including values, thresholds, messages and locations. All five corpus JSON reports also match exactly. Reproduce source snapshots with `php docs/research/control-facts-snapshot.php /tmp/soda-facts.json` from the repository root.

Delivery order: [conditions #6](https://github.com/cosmira/soda/pull/6), [project usage #7](https://github.com/cosmira/soda/pull/7), then the internal-facts PR based on #7. These are dependent PRs: review and integrate in that order.
