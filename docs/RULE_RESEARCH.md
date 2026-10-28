# Rule research

GitHub discovery snapshot: 2026-10-14. Stars are approximate counts displayed by
GitHub during research, not a guarantee of rule quality. PHP tools provide the
closest semantics; other languages provide readability ideas that must be
validated against PHP behavior before adoption.

| Project | Stars | Relevance to Soda |
|---|---:|---|
| [ESLint](https://github.com/eslint/eslint) | ~27.6k | Small syntax checks and explicit rule examples |
| [PHPStan](https://github.com/phpstan/phpstan) | ~14.1k | Type-aware correctness; avoid duplicating its type checker |
| [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) | ~13.6k | Redundant syntax and simplification checks |
| [RuboCop](https://github.com/rubocop/rubocop) | ~12.9k | Convention-driven readability policies; Ruby semantics differ |
| [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) | ~10.8k | Token-based standards; distinguish formatting from taste |
| [Rector](https://github.com/rectorphp/rector) | ~10.4k | Refactoring ideas; do not import migrations as quality rules |
| [Psalm](https://github.com/vimeo/psalm) | ~5.9k | Dead code and correctness; type-dependent checks need evidence |
| [PHP Insights](https://github.com/nunomaduro/phpinsights) | ~5.6k | Console quality, architecture and complexity checks with Laravel support |
| [PHPMD](https://github.com/phpmd/phpmd) | ~2.5k | Complexity, naming and unused code |
| [Slevomat](https://github.com/slevomat/coding-standard) | ~1.5k | Readability and removal of redundant syntax |

## Sources beyond PHP

| Project | Language | Stars | Relevance to Soda |
|---|---|---:|---|
| [Ruff](https://github.com/astral-sh/ruff) | Python | ~50.1k | Redundant control flow and local simplification; Python truthiness differs |
| [Roslyn](https://github.com/dotnet/roslyn) | C#/VB | ~20.7k | Compiler and analyzer platform, not a standalone taste rule set |
| [Checkstyle](https://github.com/checkstyle/checkstyle) | Java | ~9.6k | Readable predicates and control flow; ignore Java-specific formatting |
| [PMD](https://github.com/pmd/pmd) | Java and others | ~5.5k | Redundant branches, dead code and excessive indirection |
| [Roslynator](https://github.com/dotnet/roslynator) | C# | ~3.5k | Focused syntax simplifications and refactorings |
| [Sonar .NET](https://github.com/SonarSource/sonar-dotnet) | C#/VB | ~919 | Concrete maintainability and correctness rules |

The former [roslyn-analyzers repository](https://github.com/dotnet/roslyn-analyzers)
now directs development to `dotnet/sdk` and `dotnet/roslyn`. Use those destinations
for current implementations rather than assuming its old star count means an
actively developed standalone analyzer.

## Taylor Otwell and DHH as the selection lens

This is Soda's interpretation of their stated priorities, not a claim that either
author endorses every rule. In his
[Tuple interview](https://podcast.tuple.app/episodes/taylor-otwell-creator-of-laravel/transcript),
Otwell describes minimal code that is easy to change and avoiding elaborate
machinery for anticipated future requirements. DHH's
[Rails Doctrine](https://rubyonrails.org/doctrine) emphasizes programmer happiness,
conventions, beautiful code and integrated systems. It also explicitly values
multiple paradigms and powerful language features; a blanket prohibition on
metaprogramming would not follow from that doctrine.

Apply these principles to each candidate:

- The suggested result should read like the domain operation, with less ceremony.
- The common case should require no configuration or additional architectural layer.
- Removing duplication should not invent an interface, repository or strategy
  for code with no independent responsibility.
- A direct expression, closure, framework idiom or integrated object may be the
  right design. Do not mechanically impose one paradigm or ban useful capabilities.
- Preserve PHP effects, truthiness, reference behavior and lexical scope. Fewer
  lines are useful only when behavior and readability remain clear.

For example, Java's boolean-return simplification is admitted because it removes
branches around a predicate. Mandatory getters/setters, interfaces for every
class, universal dependency injection and splitting methods solely to satisfy
a numeric metric are not adoption criteria for Soda.

## First adopted rules

| Upstream rule | Soda rule | Admission boundary |
|---|---|---|
| PHP CS Fixer [no_useless_return](https://cs.symfony.com/doc/rules/return_notation/no_useless_return.html) | [no_useless_return](rules/no_useless_return.md) | Final direct bare return only; preserve reference contracts |
| PHP CS Fixer [no_useless_concat_operator](https://cs.symfony.com/doc/rules/operator/no_useless_concat_operator.html) | [no_useless_concat](rules/no_useless_concat.md) | Two literal operands on one line; preserve multiline layout |

Both checks have no constructor options, dependencies or additional fact
collectors. Each has a dedicated parsed-snippet test class covering accepted
boundaries, violations, source positions, diagnostic measurements and catalog
registration. Their implementations are native Soda checks, not copied fixers.

## First cross-language adoption

PMD's [SimplifyBooleanReturns](https://pmd.github.io/pmd/pmd_rules_java_design.html#simplifybooleanreturns)
and Checkstyle's [SimplifyBooleanReturn](https://checkstyle.org/checks/coding/simplifybooleanreturn.html)
inspired [no_redundant_boolean_returns](rules/no_redundant_boolean_returns.md).
It covers explicit `else` and an immediate fallback in the same statement list.
The positive replacement casts to bool when the condition's type is unknown;
Java's statically boolean condition cannot be assumed for PHP. Tests exercise
PHP truthiness and single evaluation, as well as branch work and callable boundaries.

Roslynator's [RCS1104](https://josefpihrt.github.io/docs/roslynator/analyzers/RCS1104/)
overlaps `no_unneeded_ternary`. Its [RCS1004](https://josefpihrt.github.io/docs/roslynator/analyzers/RCS1004/)
removes braces, a formatting preference with no missing Soda invariant. These
were reviewed without adding duplicate checks or importing a foreign style guide.

## Scientific evidence

Research is a source of candidates and a check on our assumptions. A GitHub star
count measures popularity; a metric correlation, accepted review or style guide
does not establish that every rewrite improves human comprehension in PHP.

| Publication | Evidence inspected | Consequence for Soda |
|---|---|---|
| Oliveira et al., [Understanding Code Understandability Improvements in Code Reviews](https://arxiv.org/html/2410.21990v2), IEEE TSE 51(1), 2025, [DOI](https://doi.org/10.1109/TSE.2024.3453783) | Java reviews: 2,401 comments, 385 understandability comments; 83.9% of suggestions accepted. Linters cover 89 of 323 inspected occurrences (27.6%). | Mine concrete accepted changes and inspect their context. Acceptance is practitioner evidence, not a controlled comprehension experiment. |
| Oliveira et al., [Evaluating Code Readability and Legibility: An Examination of Human-centric Studies](https://arxiv.org/abs/2110.00785), ICSME 2020, [DOI](https://doi.org/10.1109/ICSME46990.2020.00041) | Systematic review of 54 papers; studies use different tasks and response variables. | Distinguish preference, tracing correctness and comprehension time. Do not collapse them into one quality score. |
| Muñoz Barón, Wyrich and Wagner, [An Empirical Validation of Cognitive Complexity as a Measure of Source Code Understandability](https://arxiv.org/html/2007.12520), ESEM 2020, [DOI](https://doi.org/10.1145/3382494.3410636) | Meta-analysis of 10 studies, 427 snippets and approximately 24,000 human evaluations. Correlations are evaluated against different understandability measurements. | Complexity can guide inspection. This does not validate Soda's PHP variant, its limit of 15 or arbitrary extraction to satisfy that limit. |
| Alawad et al., [An Empirical Study of the Relationships between Code Readability and Software Complexity](https://arxiv.org/abs/1909.01760), SEDE 2018; preprint 2019 | Abstract reports 35 Java programs, 23 constructs, six readability metrics and two complexity metrics with a negative association. | Treat metric association as exploratory support, not proof that removing a particular branch helps a PHP developer. |

The TSE paper's discussion also records disagreement between experiments and
practice: studies of dead, unreachable or repeated code in C and JavaScript did
not find a significant comprehension impact, although reviewers removed such
code. Its external-validity section cautions against generalizing findings to
all languages, frameworks or teams. Soda's taste and empirical evidence remain
separate reasons; do not label a style preference as scientifically proven.

The authors' [CODE-UP dataset](https://codeupcrc.github.io/) provides review
comments and improvement artifacts for further candidate research. Follow the
specific patch, test behavioral equivalence in PHP and record counterexamples
before promoting a pattern to a standard rule.

## Boolean-comparison adoption

Checkstyle's [SimplifyBooleanExpression](https://checkstyle.org/checks/coding/simplifybooleanexpression.html)
inspired [no_redundant_boolean_comparisons](rules/no_redundant_boolean_comparisons.md).
Unlike a statically typed Java predicate, an arbitrary PHP variable or call
cannot be assumed to be bool. Soda limits this policy to language constructs
with boolean result types and retains strict checks of unknown values and
failure sentinels. No application-wide type inference is introduced.

ESLint's [no-extra-boolean-cast](https://eslint.org/docs/latest/rules/no-extra-boolean-cast)
is related but addresses conversions in boolean contexts. Ruff's
[E712](https://docs.astral.sh/ruff/rules/true-false-comparison/) also targets literal
boolean comparisons; its documented fixes can be unsafe because Python equality
may differ from truthiness. These are neighboring ideas, not claims that Soda
implements either rule identically.

[Atoms of Confusion in Java](https://arxiv.org/abs/2103.05424), Langhout and Aniche
(2021), reports an experiment with 132 computer science students. Seven of
fourteen studied patterns produced increased mistakes; ten were perceived as
more confusing or less readable. The paper reinforces the need to evaluate
patterns individually and distinguish mistakes from perceived readability.
Its novice Java population and selected snippets do not directly validate this
PHP comparison rule. Soda's admission boundary rests on PHP semantics and the
existing taste principles, with the paper informing candidate evaluation.

## Nested-condition adoption

Ruff's [SIM102](https://docs.astral.sh/ruff/rules/collapsible-if/), originating in
flake8-simplify, inspired [prefer_combined_conditions](rules/prefer_combined_conditions.md).
Soda admits only two simple questions around one nonempty block; alternative
branches, extra work, compound boolean logic and attached inner comments are
excluded. PHP tests verify left-to-right evaluation and short-circuiting over
truthy and falsey values. This is an idiomatic simplification, not a rule directly
validated by the papers above. Their evidence informs the cautious boundary.

ESLint's [no-lonely-if](https://eslint.org/docs/latest/rules/no-lonely-if)
addresses an `if` inside `else`; Soda's `no_else_branches` already covers that
layout. A separate entry would duplicate the existing policy.

## Partial boolean ternaries

Roslynator's [RCS1104 examples 3 and 4](https://josefpihrt.github.io/docs/roslynator/analyzers/RCS1104/)
replace `condition ? predicate : false` with conjunction and
`condition ? true : predicate` with disjunction. Soda's existing
[no_unneeded_ternary](rules/no_unneeded_ternary.md) now covers those shapes and
their inverse forms when syntax proves the other branch returns bool. This is
an extension of the same ternary simplification concept, with no new rule class,
identifier or configuration choice. Unknown PHP variables and calls remain
outside the transformation because logical operators always return bool.

The tests compare both results and evaluation events for all four replacements,
including PHP's falsey string `'0'`, arrays and objects. They retain rejection
boundaries for integer results, application constants and shorthand selection.

[Does Code Structure Affect Comprehension? On Using and Naming Intermediate Variables](https://arxiv.org/abs/2103.11008)
by Cates, Yunik and Feitelson (ICPC 2021) examined six mathematical functions with
113 participants. Only one function showed a significant difference between
compound expressions and meaningful intermediate names; meaningless names
slightly reduced understanding in two other cases. The authors recommend
intermediate variables with good names, while their results remain sensitive
to the task and the selected functions. This cautions against mechanically
requiring a new temporary variable or method for every expression. It does not
directly validate the ternary-to-logic rewrite above, which is admitted on PHP
semantics and Soda's preference for direct expressions.

## Architecture-first research

Following the architectural focus requested on 2026-10-15, prioritize relationships
across declarations and consumers: construction dependencies, owner behavior,
change propagation and resolved call graphs. Small syntax cleanup does not reveal
all of these problems. Each finding should identify the participating types,
actual edges and source locations, with counterexamples for legitimate collaboration.

| Tool | Ecosystem | GitHub stars, approximately | Useful approach |
|---|---|---:|---|
| [dependency-cruiser](https://github.com/sverweij/dependency-cruiser) | JavaScript/TypeScript | 7.3k | Validate a whole-project dependency graph and explain cycles |
| [ArchUnit](https://github.com/TNG/ArchUnit) | Java | 3.8k | Dependencies, slices and architecture tests over imported program structure |
| [ArchUnitNET](https://github.com/TNG/ArchUnitNET) | C# | 1.4k | Architecture tests over resolved type relationships |

These tools inform graph-based analysis. Their configurable layer/onion policies
are not imported as Soda defaults: Laravel and Rails do not require an interface
or adapter for every collaboration. The relevant invariant is a demonstrated
problem in the existing object graph, not conformance to one prescribed layering scheme.

[Are Architectural Smells Independent from Code Smells? An Empirical Study](https://arxiv.org/abs/1904.11755)
(Arcelli Fontana et al., JSS 2019) analyzes 19 code smells, six code-smell categories
and four architectural smells. Its findings indicate that architectural smells
cannot generally be derived from local code smells. This supports inspecting
relationships separately, not declaring that every high-coupling class is harmful.

[Architecture Smells vs. Concurrency Bugs: an Exploratory Study and Negative Results](https://arxiv.org/abs/2303.17862)
studies 125 releases of five systems and reports no general correlation between
architectural smells and concurrency bugs. A graph finding is not proof that a
particular runtime failure is reached; accompanying program characteristics matter.

### Required constructor cycles

The [Spring reference](https://docs.spring.io/spring-framework/reference/core/beans/dependencies/factory-collaborators.html)
describes unresolvable constructor-injection cycles. Soda's
[no_dependency_cycles](rules/no_dependency_cycles.md) now builds an additional
graph of exact mandatory constructor parameters and detects cycles inside one
module, including self-cycles. This is a concrete construction-order problem
that ordinary namespace-cycle detection can miss.

Admission requires local final types with direct public constructors and required
concrete parameter types; inheritance, traits, substitution, optional values and
unresolved bindings are not guessed. Tests cover cross-file aliases, a three-type
cycle, same-module configuration, accepted construction breaks and duplicate
diagnostic suppression. The advice favors removing a reverse dependency or
uniting one responsibility, with no requirement for a new architectural layer.
The papers justify a separate architectural lens; they do not validate this
specific PHP detector's precision or make it a simulation of container behavior.

### Repeated setup hidden in factories

[Fowler's Fluent Interface](https://martinfowler.com/bliki/FluentInterface.html)
uses Java object assembly to explain expressive construction APIs. Fluency is
about a readable domain language, not chaining setters alone. Soda extends
[repeated_configuration_sequence](rules/repeated_configuration_sequence.md) to
fresh objects returned after repeated state-only setup. This identifies a pattern
that can be spread among several factory classes without requiring another builder
class to replace them. Diagnostics retain the owner, ordered steps and consumer
locations; no automatic class deletion is inferred.

[Towards Heuristics for Supporting the Validation of Code Smells](https://arxiv.org/abs/2110.02916)
(Junionello and de Mello, CIbSE 2021) studies 12 experienced developers validating
24 Java snippets. It derives heuristics from 303 arguments for eight smell types,
including middle man and speculative generality. The study emphasizes that smell
validation depends on complementary contextual perspectives. It motivates reporting
concrete cross-consumer evidence and leaving real lifecycle or orchestration
responsibilities intact; it does not validate this PHP detector or its thresholds.

Tests cover factory classes in separate files with aliased owner imports,
minimum consumer/setup counts, different call orders, and separate
return/execution groups. They also cover effectful setters, escaping receivers,
returned aliases, computed arguments, branch boundaries, and unreachable steps
after a return. No new class, option,
dependency or public entry point is required.

## Existing coverage

PHP CS Fixer's [no_useless_else](https://cs.symfony.com/doc/rules/control_structure/no_useless_else.html)
overlaps Soda's stronger `no_else_branches`; adding another entry would duplicate
the concept. PHPMD's [code-size rules](https://github.com/phpmd/phpmd/blob/master/src/main/resources/rulesets/codesize.xml)
overlap Soda's callable/class size and complexity limits.
Slevomat's [control-structure checks](https://github.com/slevomat/coding-standard/blob/master/doc/control-structures.md)
include boolean ternary simplification already covered by `no_unneeded_ternary`.

For subsequent batches, compare the concrete upstream rule and fixtures against
the catalog first. Adopt a rule only when it expresses a missing invariant with
an actionable diagnostic and a safe PHP boundary. Add a failing public-behavior
test, implement the smallest check, document limitations and the upstream source,
then run the full tests, Soda, PHPStan and Pint. Prefer existing rules over another
name for the same policy; do not add configuration to reproduce upstream options.
