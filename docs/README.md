# Soda Documentation

Install and check a directory without creating a config first:

```bash
composer require --dev cosmira/soda
vendor/bin/soda quality src/
```

For daily use, put paths and named rules in `soda.php`, then run `vendor/bin/soda`.
`vendor/bin/soda init` optionally generates an expanded starting configuration.
Use `vendor/bin/soda list:rules` when you want to inspect rules and their defaults.
Inside this repository, use `php soda` instead of `vendor/bin/soda`.

## Start Here

| Page | What It Covers |
|------|----------------|
| [Migration](MIGRATION.md) | Namespace changes, custom contracts and preserved policies. |
| [Config](SODA_PHP_CONFIG.md) | Root `soda.php`, paths, rules, and custom rules. |
| [Project Flow](PROJECT_FLOW.md) | Execution path and responsibilities. |
| [Adding A Quality Rule](ADDING_A_QUALITY_RULE.md) | How to add project rules and built-in Soda rules. |
| [Rule Expressions](RULE_EXPRESSIONS.md) | Compound conditions and architecture constraints using phplrt. |
| [Custom Rules](PLUGINS.md) | Named PHP checks and reusable arrays of rules. |
| [Quality Report JSON](QUALITY_REPORT_JSON.md) | Machine-readable report output. |

## Rule References

| Page | What It Covers |
|------|----------------|
| [Structural Metrics](STRUCTURAL_METRICS.md) | Size, coupling, namespace, and structural smell checks. |
| [Complexity](COMPLEXITY_READABILITY_METRICS.md) | Branches, nesting, returns and exception-handling thresholds. |
| [Naming Rules](NAMING_RULES.md) | Redundant names and boolean method naming. |

## Design Notes

| Page | What It Covers |
|------|----------------|
| [Laravel Style Architecture](LARAVEL_STYLE_ARCHITECTURE.md) | Architecture direction and public API taste. |
| [Simplification Review](SIMPLIFICATION_REVIEW.md) | Ten sourced review perspectives, evidence and remaining costs. |
| [Expert Rule Proposals](EXPERT_RULE_PROPOSALS.md) | Historical review notes and proposed rule contracts. |

There is no `soda.json`, no secondary `config/soda.php`, and no separate
configurator DSL. Expression syntax lives inside named PHP check classes. Keep examples copy-pasteable and object-based.
