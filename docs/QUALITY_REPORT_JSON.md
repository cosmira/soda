# JSON reports

Use `--report-type=json` with `--report-file=` to write a machine-readable quality report. These examples
use the paths and rules in your project's [configuration](SODA_PHP_CONFIG.md).
Create the output directory before running the check:

```bash
mkdir -p build
vendor/bin/soda --report-type=json --report-file=build/soda-quality.json
```

The top-level `schema_version` is an integer. The current schema is **4**.

| Field | Type | Description |
|------|-----|----------|
| `schema_version` | int | Report schema version. Increment this for breaking output changes. |
| `passed` | bool | Quality gate result. Matches the command exit status: `true` when no violations were produced. |
| `violations` | array | Quality rule violations with file, rule, threshold, and location data. |

Each `violations[]` entry uses this shape:

| Field | Type | Description |
|------|-----|----------|
| `rule` | string | Rule id, for example `no_assignment_in_condition`. |
| `file` | string | File path where the violation was reported. |
| `method` | string|null | Method name when the rule can identify one. |
| `class` | string|null | Class-like name when the rule can identify one. |
| `line` | int|null | Source line when the rule can identify one. |
| `value` | int | Measured value for the violation. |
| `threshold` | int | Configured rule threshold. |
| `message` | string|null | Human-readable violation message when provided by the rule. |
| `recommendation` | string|null | Concrete refactoring direction for built-in rules; `null` for custom rules without catalog metadata. |

When the JSON shape changes in a major version, update `schema_version` and
this document together.


## Separate research JSON

Research checks, including `representation_churn` in the standard bundle, write
evidence with `--research-json=`:

```bash
mkdir -p build
vendor/bin/soda --report-type=json --report-file=build/soda-quality.json --research-json=build/soda-research.json
```

This separate report has schema **1**:

```json
{
  "schema_version": 1,
  "research": {
    "representation_churn": {
      "candidates": [],
      "partial": [],
      "excluded": [],
      "coverage": {},
      "limits": {},
      "profiles": []
    }
  }
}
```

`research` is an object keyed by configured research check id (an empty object
when none are configured). Each report retains candidates, partial paths,
exclusions, coverage, limits and profiles. Unknown and truncated evidence is
research uncertainty, not a violation or proof of absence. Console output shows
quality violations and the final status. Research evidence is available only
through `--research-json` and does not affect `passed` or the exit status.
`--report-type=json` remains schema 4 with the same three top-level fields and
only ordinary violations.
