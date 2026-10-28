# repeated_configuration_sequence

📝 Finds the same fresh-object setup sequence repeated across consumers and suggests a fluent object or named construction that expresses the scenario.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Mechanical setter chains such as construct, `setPeriod()`, `setFormat()`, `download()` hide a scenario behind repeated boilerplate, and every new consumer must copy the exact call order to stay correct. The rule reports a setup sequence as a review signal and advises a scenario-oriented fluent API or named constructor, while preserving the argument evaluation order and an explicit execution boundary.

A sequence only qualifies when the same ordered method sequence appears in `minOccurrences` distinct consumers, with at least `minConfigurationCalls` configuration steps followed by one public non-configuration, non-getter operation **or a direct return of the configured object**. Configuration methods must write only their own inputs or simple literals to declared owner fields, arguments must be simple values with exact declared counts, and construction plus calls must occur in one contiguous statement list. Aliases, escaping receivers, computed or unpacked arguments, unknown calls, and branch boundaries all exclude the sequence — and an already fluent chain is accepted as compliant.

Returning the configured object covers setup duplicated among factory classes. The diagnostic names every participating callable and source location. Move the repeated construction language onto the owner when those classes exist only to assemble it; do not create another builder or an interface per factory just to satisfy this finding. A factory with lifecycle management, collaborators, validation or other independent work may deserve to remain. This is a review signal about repeated setup, not proof that every containing class can be deleted.

## Examples

```php
// ❌
function daily(string $period): string
{
    $report = new Report;
    $report->setPeriod($period);
    $report->setFormat('csv');

    return $report->download();
}

// ✅
function daily(string $period): string
{
    return Report::for($period)->asCsv()->download();
}
```

A single consumer or one-call setup is not reported. Neither is a sequence where the receiver escapes mid-way, such as `consume($r)` between setters. The detector cannot prove a scenario from one occurrence. The ❌ shape requires the same ordered sequence in `minOccurrences` distinct consumers across the analyzed paths; the default is `3`. The `Report` type must also be declared there. The single consumer above shows a repeating unit, not a complete triggering example.

The same repetition can hide behind separate construction classes:

```php
// ❌ Repeating unit in DailyReport, MonthlyReport and AnnualReport.
final class DailyReport
{
    public function make(string $period): Report
    {
        $report = new Report;
        $report->setPeriod($period);
        $report->setFormat('csv');

        return $report;
    }
}

// ✅ The owner expresses construction; no setup-only class is needed.
$report = Report::for($period)->asCsv();
```

This example requires the same setup in three distinct consumers and analyzed
`Report` methods with proved state-only bodies. Return and execution scenarios
are counted separately; different method orders do not form one group. Fluent
design should express the scenario in domain terms, rather than merely making
every setter return `$this`. Keep any real factory responsibilities and review
constructor side effects and contracts before changing construction.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `minOccurrences` | `3` | Minimum distinct consumers repeating the same ordered setup scenario. Must be at least `2`. |
| `minConfigurationCalls` | `2` | Minimum state-only setup calls before execution or returning the configured object. Must be at least `2`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\RepeatedConfigurationSequence;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepeatedConfigurationSequence(minOccurrences: 3, minConfigurationCalls: 2),
    ]);
```

## Related rules

- [methods_follow_call_order](methods_follow_call_order.md) — keeps declared method order aligned with the execution order a fluent API would encode.
- [no_trivial_factories](no_trivial_factories.md) — flags factories that add no value over direct construction, the inverse side of construction design.
- [max_ask_then_tell_patterns](max_ask_then_tell_patterns.md) — bounds ask-then-tell usage, another shape of driving an object from the outside.
