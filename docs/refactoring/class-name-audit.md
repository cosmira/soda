| Class Group | Decision | Rationale |
| --- | --- | --- |
| `*Definitions`, `*Rules`, `*Visitors`, `*Handlers` | accepted-laravel-style | These classes model groups, registries, packs, or coordinated handlers. The plural noun is the subject, not an accidentally plural entity model. |
| `*Metrics`, `*Statistics`, `*Factors`, `*Limits` | accepted-laravel-style | These are measurement/value objects where the plural domain term is idiomatic and clearer than an artificial singular. |
| Rule classes such as `MaxReturnStatements`, `NoEmptyCatchBlocks`, `MaxMethodsPerClass` | accepted-laravel-style | Rule class names describe the rule being enforced; the plural part names the measured collection. |
| `QualityAst*Visitors` packs | accepted-laravel-style | These are bundles of multiple PhpParser visitors, so plural naming is intentional. |
| `NoNestedArrayAccess`, `ListOnlyArrayStrictness` | accepted-laravel-style | These are singular rule/policy concepts despite ending in `s`/`ss`. |

No production class currently violates PascalCase in `src/`.
