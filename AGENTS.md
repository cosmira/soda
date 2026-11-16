# AGENTS.md

soda is a PHP quality tool whose product is *taste*: it forces expressive minimal
APIs, convention over configuration, and a small API surface. Your job is to make
this codebase more like Laravel or Rails core, not more like enterprise PHP.
When in doubt, choose the option that deletes code.

## Commands

```bash
composer test          # full suite; run before finishing any change
composer test:unit     # unit suite only
vendor/bin/phpunit tests/unit/NoVacuousDocblocksTest.php   # one file
composer quality       # soda judges its own source; it must pass
composer phpstan && composer pint:test                     # static gate
```

Run the tests. Do not finish with a red suite, and do not weaken an assertion
to make it green.

## Design principles

Every principle below is verifiable. If a violation is mechanical, add or use a
soda rule instead of writing prose.

**Small API surface.** Never add a public method, option, or class unless a
failing test demands it. Default to `private`. Three similar lines beat a wrong
abstraction; an interface with one implementation is speculation.

**Convention over configuration.** A new config key is a defect, not a feature.
The common case must work with zero configuration. If users must read docs to
do the common thing, fix the design.

**Delete code.** When touching existing code, prefer removing an option, class,
or entry point over extending it. The best diff is a red one. Never add
`unset($variable)` superstitions, defensive fallbacks like `?? new Runner`, or
nullable constructor seams for tests — inject through the container instead.

**Name the thing, not its job category.** `Manager`, `Helper`, `Util`,
`Service`, `Handler`, `Wrapper`, `Evidence`, `Probe` are rejected by
`NoDumpsterSuffixes`. Boolean methods read as questions (`isExpired()`).
No `get`/`set` prefixes without real asymmetry.

**Comments carry invariants, not narration.** Comment only when context is not
readily apparent from the code. Never narrate code, repeat the name, or invent
a rationale. `NoVacuousDocblocks` rejects descriptions built from filler verbs
(`stores`, `defines`, `holds`) and generic nouns (`this instance`).

**Pick one word per concept.** `analyse` and `analyze` do not coexist. Check
existing vocabulary with `grep -rn "analy[sz]e" src` before inventing a variant.

## Correct / Avoid

```php
// Correct: one entry point, real dependency, no fallback
public function analyze(array $paths, ?string $config = null): QualityResult

// Avoid: parallel entry points and nullable seams for tests
(new QualityAnalyser)->analyse($files);              // duplicate of Analyzer::analyze
public function analyse(?Runner $engine = null)      // test-only parameter
```

```php
// Correct: docblock earns its place
/** Byte offsets of every signature entry, indexed by declaration id. */
private array $signature;

// Avoid: generated filler
/** Stores signature for this analysis instance. */
private array $signature;
```

## Known anti-patterns in this codebase

These were removed once; do not reintroduce them.

- Duplicate entry points (`QualityAnalyser` next to `Analyzer`).
- A static factory that mutates properties instead of constructing
  (`BooleanMethodPrefix::standard()`); explicit constructors in the standard configuration define variants.
- Framework `Collection` inside domain results; domain returns plain arrays.
- Special-case `match` arms for a single rule inside generic catalogs.
- Dead container wiring: never register a binding that nothing resolves.

## Where things live

- `src/Contracts/Check.php` — the rule contract: `id()`, `checkFile()`,
  `checkProject()`, `requiredAnalyses()`.
- `src/Rules/<Section>/` — concrete rules are discovered automatically;
  `Reporting\Rule` supplies metadata and `resources/standard.php` selects defaults; `docs/rules/<id>.md` documents it;
  `docs/STRUCTURAL_METRICS.md` or `docs/NAMING_RULES.md` lists it.
- `src/Analysis/` — fact collectors; facts are scalars, never live ASTs.
- `tests/unit/` — one test class per rule, built on `CheckFixture` and real
  parsed snippets; tests target the public API.

## Verification contract

Finished means: tests green, `composer quality` green, and your diff is not
larger than the problem you were given. Rules enforced by tools beat rules
stated in prose — when you find yourself violating a principle repeatedly,
automate it as a soda rule.

## Boundaries

- Ask first: new public API, new composer dependency, new config surface, new
  `src/` namespace.
- Never: bypass the quality gate, edit generated files under `vendor/`,
  weaken `phpstan.neon` or `infection.json5`, or leave `TODO` without a
  tracking issue.
