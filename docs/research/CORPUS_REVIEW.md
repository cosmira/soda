# Practical-rule corpus review

The decision is to keep **Cognitive Complexity optional**, retain cohesion as
an inspectable research measurement, and **not ship a disconnected-method-groups
rule**. No candidate joins the 69-rule standard set. Neither a trait quota nor a
fluent/value-object quota follows from these results.

## What was inspected

The [fixed manifest](corpus.json) pins five repositories and their production
roots. Discovery used Soda, Laravel and Monolog; Symfony Console and Guzzle were
held out until the [discovery decision](discovery-decision.md) was written.
No upstream project was installed or executed. Shipped testing utilities such as
Laravel's Testing concerns and Symfony's Tester are included; project test and
resource directories are excluded. No independent business application was
available. This is a library/framework corpus, not a representative PHP sample.

The selector uses a fixed SHA-256 ordering within each project and rule, then
round-robin selection across rules. Quotas are 20/40/10 discovery findings and
15/15 held-out findings, plus five non-finding callables per project. Exactly
**100 findings and 25 non-findings** were inspected. The source of each selected
callable/type was read, including surrounding exception scopes and relevant
inherited protocol context. These are judgments by **one assistant**, not an
independent expert panel, endorsements by project authors, or a comprehension
experiment. No proposed upstream refactoring was implemented or timed.

The machine-readable records contain the rule, exact position/value, explanation,
judgment and source link pinned to the commit:

- [70 discovery findings](discovery-review.json), [15 discovery non-findings](discovery-nonfindings.json).
- [30 held-out findings](held-out-review.json), [10 held-out non-findings](held-out-nonfindings.json).
- [Complete corpus counts](corpus-summary.json).

A finding was marked `review_candidate` when inspection suggested a concrete
local simplification worth testing. `limited` means the control flow may be
intrinsic or extraction offers no clear benefit. `not_actionable` means the
proposed structural pressure was unjustified in that inspected scenario. These
labels do not establish a measured accuracy rate or universal truth about the
upstream code.

## Results

| Project | PHP files | Callables | Types | Cognitive >15 | Groups >1, methods ≥4, no unknowns | Rethrow / empty finally |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Soda | 167 | 828 | 168 | 1 | 31 | 0 / 0 |
| Laravel | 1,680 | 16,173 | 1,433 | 63 | 49 | 1 / 1 |
| Monolog | 125 | 744 | 117 | 13 | 4 | 0 / 0 |
| Symfony Console, held out | 177 | 1,389 | 158 | 55 | 19 | 0 / 0 |
| Guzzle, held out | 70 | 754 | 64 | 35 | 3 | 0 / 0 |
| Total | 2,219 | 19,888 | 1,940 | 167 | 106 | 1 / 1 |

These are final collector results, **275 candidate findings**, with zero parse
errors. The initial discovery collector produced two additional cohesion findings.
Discovery case D050 exposed an omitted implicit `(string) $this` → `__toString`
edge. That edge was implemented and covered by equivalent explicit-call tests;
iteration/clone of this is now explicitly unresolved. The two eliminated findings
were Laravel's `PotentiallyTranslatedString` and its creating concern. D050 remains
in the original review sample as the evidence that led to the correction; the
sample was not silently replaced. The other removed finding was not sampled.
Thresholds were never adjusted, and no algorithm changes were made in response to
held-out judgments. Moving the rejected threshold into the research harness does
not change its expression or results.

| Inspected signal | Discovery | Held out | Judgment |
| --- | ---: | ---: | --- |
| Cognitive complexity | 26 | 20 | 22 + 15 concrete review candidates; 4 + 5 limited cases |
| Disconnected groups | 42 | 10 | All 52 did not justify a class split; one also revealed the collector omission |
| Exception checks | 2 | 0 | Both worth reviewing; sample too small for broad conclusions |

The stratified sample overrepresents rare rules and Soda relative to Laravel.
Repeated Check/null-handler/helper protocols are correlated examples. In
particular, **52/52 is a description of this inspection, not population precision**.
Only 764 of 1,940 composed type graphs had no recorded unknown relationships;
missing external dependencies, dynamic calls and private inheritance restrict
coverage. Even an apparently complete syntax graph does not certify that all
runtime behavior has been resolved.

## What Soda should encourage

Cognitive findings often suggest **local operations**, not more objects:
Monolog's GELF formatter repeats extra/context normalization (D061); its line
formatter repeats placeholder handling (D063); Symfony's table width calculation
can name colspan expansion (H001). In contrast, plural formulas (D035), guarded
schedule processing (D049), a placeholder dispatch table (H022), and explicit TLS
rejection reasons (H025) have legitimate control flow. A smaller score is not a
sufficient reason to scatter those operations across files.

Cohesion fails the intended design incentive. Soda's fluent configuration has
separate path/check state (D002). Laravel's compiler and IO concerns have related
operations without shared fields (D040/D056). Symfony's fluent TableStyle and
CompletionSuggestions intentionally expose independent settings and lists
(H002/H014). Guzzle's ProxySelection and TransferStats are coherent values/records
(H017/H019). Null handlers and interface lifecycle hooks add disconnected groups
without adding business responsibilities. **Do not split these just to connect a
graph.** The executable design fixtures remain the regression contract. Efferent-coupling
advice also stops mandating a facade merely to lower the dependency count; its
calculation and threshold are unchanged.

Non-findings also expose limits: repetitive defensive fact guards remain verbose
at low cognitive complexity (N005); a scope-bound reflection closure has little
branching (N008); an autosaving destructor is simple numerically but hides a
lifecycle effect (M010). Random callable non-findings cannot estimate recall for
catch/finally constructs or class-level cohesion. Additional targeted tests cover
shared-logger false confidence, readonly data, trait resolution, dynamic unknowns,
guards, nested callables and exception counterexamples.

## Reproduce and extend

Obtain each manifest commit in `/tmp/soda-corpus/<name>` using a read-only archive
or checkout. Do not substitute the current default branch. With Soda dependencies
installed, run:

```sh
php docs/research/corpus.php /tmp/soda-corpus /tmp/soda-corpus-results discovery
python3 docs/research/select_review.py /tmp/soda-corpus-results discovery
php docs/research/corpus.php /tmp/soda-corpus /tmp/soda-corpus-results held-out
python3 docs/research/select_review.py /tmp/soda-corpus-results held-out
```

The final discovery selector naturally differs from the archived initial sample
because the collector correction removed D050. The JSON records preserve that
initial evidence. Measurements are reproduced through the existing Runner and
FileFacts/ProjectFacts, with a research Check capturing facts; there is no second
engine or Java/Python runtime requirement in the library. Python only selects the
review sample. The rejected threshold class is explicitly required by this
harness and is absent from the production classmap/catalog.

Timing and allocated-memory observations are recorded for transparency. Some
runs overlapped other checks, and PHP allocator pages were reused between projects;
these are **not controlled performance comparisons**. The project's existing
performance/memory gate is run separately. For stronger usefulness evidence,
next obtain an independent application, have multiple people inspect blind
before/after variants, and measure task accuracy/time. Until then, do not promote
these experimental signals to mandatory architectural rules.
