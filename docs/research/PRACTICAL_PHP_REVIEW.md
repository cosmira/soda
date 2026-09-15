# Practical PHP: diagnostic comparison

Baseline: `ec3449420bee5ed705bc95646bed343f1d7fe6fe`. Corpus revisions and selection are pinned in [corpus.json](corpus.json). Reproduce with [practical-php-corpus.php](practical-php-corpus.php). Only the two changing rules are selected.

## Conditions

| Project | Files | Before | After | Added locations | Removed locations |
| --- | ---: | ---: | ---: | ---: | ---: |
| soda | 167 | 0 | 0 | 0 | 0 |
| symfony-console | 177 | 703 | 189 | 12 | 526 |
| laravel | 1680 | 3363 | 897 | 18 | 2485 |
| monolog | 125 | 297 | 36 | 2 | 263 |
| guzzle | 70 | 720 | 85 | 0 | 635 |

Location identity is rule/file/line; counts can differ from location totals when expressions share a line. `unused_methods` is identical in this first step.

Inspection of added and removed examples identified these changes:

- Laravel Gate predicates with multiple `is_*` calls and homogeneous AND now remain inline. Negated `is_null` checks also stop producing findings.
- Symfony Application conditions combining `defined`/`isSupported`, negated `is_array`/`instanceof`, and plain exception predicates no longer need intermediary names.
- Monolog ErrorHandler `isset`, simple conjunctions and literal-array `in_array` predicates are accepted.
- Guzzle DigestAuth simple comparisons joined by one operator family are accepted.
- Laravel Gate ternary callback arguments and CreatesUserProviders ternary computations remain explicit nested work.
- Symfony DateTimeValueResolver computes a ternary-indexed receiver inside its condition and is reported.
- Monolog JsonFormatter/NormalizerFormatter increment a comparison operand: these remain computed operands.
- Single arithmetic truth tests and error-suppressed operations are outside the simple-value/predicate grammar and can add findings. This is a structural rule, not proof that every reported expression is difficult for a person.

The first trial incorrectly added findings for plain assignments and casts around predicates. Those cases became regression tests and were corrected before the final run. Compound assignment itself is likewise left to the existing assignment policy. This review is an assistant inspection of representative changes, not an independent human readability study.

## Method usage

Pending the second implementation step.
