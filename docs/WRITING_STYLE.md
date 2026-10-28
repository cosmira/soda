# Writing style

Write for a PHP developer who wants to check an existing project. Start with the
task, show a complete example, explain its result, then introduce options and
analysis boundaries. Put implementation details in the contributor guides.

## Editorial model

The local `Laravel` profile models the practical presentation in
[Laravel Installation](https://laravel.com/docs/installation),
[Configuration](https://laravel.com/docs/configuration), and
[Collections](https://laravel.com/docs/collections). Follow that sequence and tone
when writing new pages. Do not copy their sentences or examples.

Taylor Otwell's [public description of his work](https://taylor.laravel.cloud/)
emphasizes simpler solutions and care for the developer's experience. These
public examples inform this project's template. This is a Soda profile, not an
official profile written or endorsed by Otwell or Laravel.

Use direct instructions, plain words, sentence-case headings, and consistent
technical names. Explain a rule's motivation before its formal limits. A code
example that proposes a refactoring must preserve values, effects, and evaluation
order. A shorter method alone does not establish better behavior.

## Install and run Vale

Install [Vale](https://vale.sh/) with Homebrew:

```bash
brew install vale
```

The CI job pins Vale 3.24.0. Other platforms can install that version from the
[release binaries](https://github.com/vale-cli/vale/releases/tag/v3.24.0).
Confirm the installed version, then run the same checks locally:

```bash
vale --version
vale --no-global test --coverage .github/styles
bash .github/styles/test-scope.sh "$(command -v vale)"
vale --no-global README.md docs
```

The profile and its tests are committed under `.github/styles/Laravel`. They
need no downloaded style packages or `vale sync`. The `--no-global` flag keeps
personal Vale settings out of the project check.

## Enforced rules

The word budgets below are Soda's enforceable limits, not limits published by
Laravel. Sentence case is the project's heading convention.

| Rule | Requirement |
| --- | --- |
| PlainWords | Prefer direct words such as "use", "start", and "before". |
| Filler | Remove introductions and assumptions that a task is simple. |
| Terms | Use the canonical spelling of Soda, PHPDoc, PHPStan, GitHub, JSON, and YAML. |
| SentenceLength | Keep a sentence to 35 words or fewer. Split separate ideas. |
| ParagraphLength | Keep a paragraph to 120 words or fewer. Give each paragraph one purpose. |
| Headings | Use sentence case. Technical identifiers and product names retain their spelling. |

All six rules report errors. Every diagnostic fails the CI job, including one in
an unchanged document. Rule tests include passing prose, failing prose, literal
identifiers, and fenced code. Coverage requires every rule to produce a finding
in a test case. The scope test verifies that navigation pages remain checked
and historical research records remain excluded.

## Scope and review

The command checks `README.md` and Markdown under `docs/`. It excludes
`docs/research/`, which holds historical studies, third-party observations,
frozen evidence, and reproduction records. Its active navigation page,
`docs/research/README.md`, remains checked. Editing those records to satisfy a
current prose rule could change the experiment being recorded.

Vale ignores Markdown code blocks and inline code when checking prose. The
existing spelling check remains responsible for spelling. Do not add file-level
waivers or raise limits to make an active guide pass; improve its wording.

Vale cannot verify that an example preserves behavior, that an explanation is
true, or that a reader can find the right page. Review those properties and run
executable examples alongside the prose check. A clean lint result complements
editorial review rather than replacing it.
