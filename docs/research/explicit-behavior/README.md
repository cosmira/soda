# Explicit behavior candidates

See the [RFC](../../rfcs/explicit-behavior-rules.md) for the specification,
principle matrix, examples and admission decision. These are frozen research snapshots of checks now enabled by default. The original admission result was:
550 production files yielded zero findings, so neither met the minimum of 20
reviewed findings. Precision is unknown. The owner subsequently requested standard inclusion;
production classes live under `src/Rules`, while these snapshots remain unchanged.

## Run the tests

```sh
vendor/bin/phpunit tests/unit/ExplicitBehaviorCandidatesTest.php
```

## Frozen experimental snapshots

From a Soda checkout, a PHP configuration can load the candidates manually:

```php
require_once __DIR__.'/docs/research/explicit-behavior/NoTrivialFactories.php';
require_once __DIR__.'/docs/research/explicit-behavior/NoRepeatedCompoundConditions.php';

return \Cosmira\Soda\Config\Soda::configure()->withPaths(['src/'])->with([
    new \Cosmira\Soda\Research\ExplicitBehavior\NoTrivialFactories(),
    new \Cosmira\Soda\Research\ExplicitBehavior\NoRepeatedCompoundConditions(minMethods: 3),
]);
```

Save as a separate experimental config and run `php soda --config=experimental.php`.
These classes are intentionally outside production autoload and discovery.
An enabled finding uses the usual exit status 1 and JSON schema 4. No autofix.

## Reproduce the corpus

Provide `monolog`, `symfony-console` and `guzzle` checkouts under a donor directory
at the commits in `manifest.json`. The Soda input is this checkout's `src`;
`results.json` records its working-tree hash as well as its HEAD. Donor roots and
excluded directories are in the manifest and harness. No donor code is executed.

```sh
php docs/research/explicit-behavior/corpus.php discovery /tmp/soda-corpus /tmp/soda-explicit-results
php docs/research/explicit-behavior/corpus.php held-out /tmp/soda-corpus /tmp/soda-explicit-results
python3 docs/research/explicit-behavior/select_review.py /tmp/soda-explicit-results /tmp/selected.json
```

The harness refuses changed detector hashes. Compare project tree hashes in its
output against `results.json` before treating a rerun as the same corpus.
Selection takes five classes per project and candidate by deterministic SHA-256
order, filling any shortage from the remaining project/hash order. The full
lookalike methods go to temporary output; the committed review stores brief
excerpts and source hashes. The excerpts are from the identified Soda, Monolog,
Symfony Console and Guzzle versions; upstream licenses continue to apply.

The committed review is one assistant's inspection through DHH and Otwell's
published principles, not their participation or an independent expert panel.
Zero findings means no before/after corpus finding could be reviewed; executable
synthetic examples cover detector behavior only. Many selected nonfindings are
broad lookalikes rather than near misses. More application code is needed before
considering admission, and the original frozen run must remain distinguishable
from any later algorithm revision.

## Validation of the original research revision

- `composer test`: 814 tests, 8,416 assertions passed (including 56 new candidate tests).
- `composer test:performance`: 2 tests, 6 assertions passed.
- `composer pint:test`, `composer rector:check`, `composer quality`: passed.
- PHPStan checked `src` and both research detector files with no errors.
  `--debug` was used because this sandbox disallows PHPStan's parallel TCP listener.
- Frozen detector hashes and the 20 + 20 review-record counts were verified.

The existing Rector configuration emits deprecation notices for skipped rules;
its check still passes. No configuration changes were made to hide them.
