# Documentation

Soda reports PHP code structure and maintainability problems. Start with a check
of your own code, then choose the rules your project needs.

## Getting started

1. [Installation](INSTALLATION.md). Install Soda and run your first check.
2. [Configuration](SODA_PHP_CONFIG.md). Choose source paths, exclusions, and rules.
3. [Continuous integration](CI.md). Run the same check on every change and save reports.

Upgrading an existing setup? See the [migration guide](MIGRATION.md).

## Rules

If you have a rule ID from a report, use the [rule reference](rules/README.md).
Each individual page is the main reference for its behavior, examples, options,
and limits. Use `vendor/bin/soda list:rules` to inspect the installed catalog.

If you are choosing your team's policies, start with the topic guides below.
They connect related checks and link to the individual rule pages:

- [Code structure](STRUCTURAL_METRICS.md).
- [Conditions and control flow](COMPLEXITY_READABILITY_METRICS.md).
- [Expressions and local state](EXPRESSION_AND_LOCAL_STATE_RULES.md).
- [Owner behavior](OWNER_BEHAVIOR_RULES.md).
- [Naming](NAMING_RULES.md).

These are documentation topics. They are not arguments for
`RuleCatalog::checks()`, whose catalog sections are `structural`, `complexity`,
and `naming`. See [Configuration](SODA_PHP_CONFIG.md#rules) for rule selection.

## Reports

[JSON reports](QUALITY_REPORT_JSON.md) describe the machine-readable findings and
separate research output. Research findings do not fail the quality check.
See [TeamCity reports](QUALITY_REPORT_TEAMCITY.md) for build-log integration.

## Write your own rules

- [Custom checks and rule sets](PLUGINS.md). Add a rule class or share a set of rules.
- [Rule expressions](RULE_EXPRESSIONS.md). Use the expression language inside a named rule.
- [Adding a quality rule](ADDING_A_QUALITY_RULE.md). Contribute a built-in rule with tests and documentation.
- [Writing style](WRITING_STYLE.md). Follow the editorial template and run Vale before submitting documentation.

## Understand the design

- [Practical design](DESIGN_PHILOSOPHY.md) explains the choices behind the recommendations.
- [Project flow](PROJECT_FLOW.md) follows parsing, fact collection, checks, and reporting.
- [Public API and architecture](LARAVEL_STYLE_ARCHITECTURE.md) describes module boundaries.
- [Analysis evidence (Russian)](ANALYSIS_EVIDENCE.md) explains how findings handle uncertainty.
- [Representation consistency (Russian)](REPRESENTATION_CONSISTENCY.md) examines conversion boundaries and connected data flows.

## Research

The [research index](research/README.md) contains studies, hypotheses, pinned
corpora, and reproduction tools. These records describe the versions studied;
they are not the reference for current rule behavior.
