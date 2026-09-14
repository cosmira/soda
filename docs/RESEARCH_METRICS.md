# Research measurements: cognitive control flow and cohesion

These measurements are **opt-in**. They do not replace the standard's existing
69 checks, define a general quality score, or prescribe traits/objects by quota.
They use the existing PHP parse and Check execution path. `list:metrics` exposes
the expression fields and calculation methods; `list:rules` marks cognitive
complexity as optional. The disconnected-groups threshold was rejected after
[corpus review](research/discovery-decision.md) and exists only in
`docs/research/MaxDisconnectedMethodGroups.php` to reproduce that experiment.
It is not a built-in rule or a recommended configuration.

```php
new \Cosmira\Soda\Rules\Complexity\MaxCognitiveComplexity(15)
```

## Soda PHP cognitive v1

The score starts at **0** for each callable. `CognitiveComplexity::collect()`
returns contributions containing source line, increment and reason. The check's
message includes every contribution; `value` is their actual sum. A zero limit
accepts linear code; a negative limit is invalid.

The design was informed by [Sonar's Cognitive Complexity specification, v1.7](https://www.sonarsource.com/docs/CognitiveComplexity.pdf)
and the [Aibolit implementation](https://github.com/cqfn/aibolit/blob/1de37051b74fc61ce9313f350487d5807db732db/aibolit/metrics/cognitiveC/cognitive_c.py).
This is an independently implemented **PHP variant**, not a claim of identical
Sonar/Aibolit scores. Sonar's recursion-cycle and nested-lambda policies are not
fully implemented: Soda separates nested callable bodies and recognizes only
syntactic direct recursion. Cross-callable totals should not be used as a quality
rating. [Empirical work on Cognitive Complexity](https://arxiv.org/abs/2007.12520)
supports investigating understandability, but does not validate Soda's variant,
its threshold or a resulting refactoring.

The following table specifies Soda's implementation and tested PHP decisions:

| Construct | Contribution |
| --- | --- |
| `if`, loops, `catch`, full ternary, `switch`, `match` | 1 + enclosing structural-body depth |
| `elseif`, `else` | 1; their bodies use the original if's body depth |
| Conditions/loop headers | Evaluated at the enclosing depth; nested structures in branch bodies use the incremented depth |
| Switch cases / match arms | No per-arm point; their nested bodies can contribute |
| Boolean binary run | 1 for each change between AND, OR and XOR in source order. Word and symbolic forms are normalized after PHP parsing. Negated subexpressions start their own runs. |
| `goto`, literal `break N` / `continue N`, N > 1 | 1, without a nesting increment |
| Named direct recursion, `$this->sameMethod()`, `self::sameMethod()` | 1 per callable, irrespective of call-site count; syntactic receiver identity is not a proof of dispatch at runtime |
| `return`, `throw`, plain break/continue, try/finally | No point by themselves |
| `??`, nullsafe access, shorthand `?:` | No point by themselves; nested expressions can still contribute |
| Nested functions, closures, arrows, anonymous methods, enum/trait methods | Separate callable with base 0; body excluded from the enclosing score |
| Mutual/dynamic recursion | Not resolved or charged in v1; no claim of Sonar equivalence |

Named method/function scores live directly in `FileFacts::$metrics['methods']`.
`cognitiveExtras` holds only scopes absent from legacy method metrics. Ordinary
method expressions retain their existing named scope; MaxCognitiveComplexity
includes the extras through the shared ExpressionCheck row hook. Other size and
complexity checks do not acquire new anonymous rows. Top-level statements outside
callables are not assigned a fictitious method score.

## Cohesion: observed graph, declared and composed views

`Cohesion::collect()` records literal instance-field accesses and local calls as
scalar/array facts. `Cohesion::resolve()` runs after all project declarations are
available. Each type retains **declared** and **composed** views, including each
trait separately. No AST, source, per-edge object, graph library or second runner
is retained in project data.

Vertices are concrete instance methods, with constructors, static methods and
abstract declarations excluded. Private/protected methods participate in
components; only public eligible methods participate in TCC. Reads and writes of
a literal `$this->field` both connect to that field. Two methods connect through
a shared field or a literal local method call. A literal `(string) $this`
adds the implicit `__toString` call. Calls on collaborators do not
become local-method edges. Components are found by traversal of this graph.

TCC counts public-method pairs sharing a literal instance field **directly**,
divided by n(n−1)/2. Method-call reachability does not contribute to its numerator.
With fewer than two public methods the ratio is **undefined** (`null`), not 0 or
perfect cohesion. Expressions expose the numerator `tcc_pairs` and denominator
`tcc_possible_pairs` as exact counts; divide only when the denominator is positive.
`method_groups`, `cohesion_methods` and `cohesion_unknown` expose the other counts.
Cohesion expression values refer to the project-resolved class stage.

Literal trait selections, aliases, visibility changes, nested uses and ordinary
known inheritance are composed. PHP type/method names are case insensitive;
property names retain case. Missing/cyclic types, ambiguous trait methods,
property redeclarations/compatibility, inherited private-member identity,
property hooks, dynamic/parent/late-static calls, missing local targets or fields,
nested callables that might capture this, iteration/clone of this, and passing/aliasing this are explicit
unknown reasons. Such graphs still show observed groups but the research threshold check
**does not emit a violation**. This conservatism can miss opportunities; an
unknown relation must never become evidence of an absent relation.

In the research harness, the minimum method count (4) filters its candidate only; it does
not change graph facts. Negative group limits and minimum counts below 2 are
invalid. Declared enum/anonymous graphs are available to this candidate without
changing the legacy named-class counts or default rule scopes.

A shared logger can make unrelated operations connected. A readonly record,
stateless utility or cohesive concern can legitimately have disconnected methods.
The corpus confirms that these groups should not become a built-in prohibition.
Keep them for inspection, not as a business-responsibility count or a mandate
to create classes. There is no TCC-based prohibition.

The [jPeek sample family](https://github.com/cqfn/jpeek/tree/eb89639b5ab0f989d239a372c759be083c07f8e6/src/test/resources/org/jpeek/samples)
and [Aibolit's graph](https://github.com/cqfn/aibolit/blob/1de37051b74fc61ce9313f350487d5807db732db/aibolit/utils/cohesiongraph.py)
informed boundary cases. No donor graph or formula is copied: the reviewed jPeek
LCOM4 XSL is not a connected-components algorithm, and its Java LCOM4 calculus is
unfinished. The PHP tests independently specify integer components, bridge calls,
constructor exclusion, unknown relationships and the declared/composed distinction.

## Reproduction and acceptance

See the [100-finding / 25-non-finding review](research/CORPUS_REVIEW.md) for
the final decision, pinned sources and limitations.

The fixed [corpus manifest](research/corpus.json) separates discovery from held-out
projects and records candidate limits before inspection. Obtain the indicated
commits under `/tmp/soda-corpus/<name>` without installing/running the projects,
then run `php docs/research/corpus.php /tmp/soda-corpus /tmp/soda-corpus-results discovery`.
Use `held-out` only after recording discovery judgments. The evidence collector
is a normal Check run by the existing Runner, not a parallel analysis engine.

Unit tests cover source formatting and local-variable renaming, positive cases,
legitimate counterexamples, actual contributions, scalar project data and parity
between full/requested analyses. The corpus review records actual sample sizes
and uncertainty. Measurements and automated tests alone are not evidence that
users find the recommended changes easier to understand.
