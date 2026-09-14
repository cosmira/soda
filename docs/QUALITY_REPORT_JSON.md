# Report JSON

Use `--report-json=` to write a machine-readable quality report:

```bash
php soda quality --report-json=build/soda-quality.json
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
