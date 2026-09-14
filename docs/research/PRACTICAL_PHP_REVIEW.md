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

The condition findings are byte-for-byte identical to step 1. Method findings change as follows:

| Project | Before | Project usage |
| --- | ---: | ---: |
| Soda | 0 | 0 |
| Laravel | 477 | 0 |
| Monolog | 69 | 0 |
| Symfony Console | 40 | 0 |
| Guzzle | 1 | 0 |

The 587 removed method findings combine resolved calls/contracts with conservative uncertainty; **zero findings is not proof that the corpus has no unused code**. Missing dependencies are not silently loaded. The analysis deliberately abstains when affected composition or receivers cannot be resolved.

Reviewed examples:

- Laravel `Broadcaster::verifyUserCanAccessChannel` is called through `parent` in Redis/Ably/Pusher broadcasters; `formatChannels` is called by multiple descendants. Cache lock implementations retain the abstract `Lock` contract across files.
- Monolog `LineFormatter::normalizeException` participates in dispatch from `NormalizerFormatter`; `AbstractSyslogHandler::toSyslogPriority` is called by the concrete syslog handlers.
- Symfony command `configure`/`initialize`/`execute` implementations participate in their inherited command lifecycle. `Descriptor::removeHiddenOptions` has callers in Text/Json/Markdown/Xml/ReStructuredText descriptors.
- Guzzle `CurlMultiHandler::tickInQueue` is passed through `Closure::fromCallable([$this, 'tickInQueue'])`, which the previous callback collector missed.

An intermediate run added two incorrect Symfony findings. `ProgressBar::getStepWidth` has a caller on a closure parameter declared `self`. `Option::handleUnion` is reached on a factory result obtained with `self::class`. Both became regression cases: explicit parameter types resolve receivers, while the latter remains conservative factory-result uncertainty. The final run has no added method findings.

Tests additionally prove that untyped foreign receivers cannot hide local unused methods, that uncertainty in one type leaves unrelated types reportable, and that alias/override/private dispatch preserves original declaration identity. These synthetic counterexamples matter because the corpus itself contains no final method findings. Review remains qualitative and bounded; arbitrary variable data flow, union/return types and reflection are not claimed to be solved.


## Internal fact validation

Removing redundant checks changes no diagnostic. Full JSON reports for all five pinned projects are byte-for-byte identical to the project-usage step. A separate snapshot covers all tracked PHP test sources and generated condition examples: 148 inputs, 147 identical diagnostic objects across the three control-flow rules. The concurrent uncommitted `ExplicitBehaviorCandidatesTest` is excluded from this comparison. Use [control-facts-snapshot.php](control-facts-snapshot.php) to reproduce it with development dependencies installed.
