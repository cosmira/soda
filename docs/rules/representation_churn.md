# representation_churn

📝 Flags value paths where a primitive is extracted from a representation object, processed, and reconstructed into the same representation, for inspection before changing its representation.

This research check is enabled in the `standard` configuration. It never creates
quality violations and cannot fail CI. Export its evidence with `--research-json`.

## Why

When a value is extracted from an object, transformed, and wrapped again, the
conversion can be spread across several callers. This check collects evidence
for reviewing whether the conversions have a useful purpose.

## Examples

```php
// Connected path to inspect; this is not a quality violation
final readonly class Str
{
    public function __construct(private string $text) {}

    public function value(): string
    {
        return $this->text;
    }
}

final class Producer
{
    public function load(string $input): Str
    {
        return new Str($input);
    }
}

final class Normalizer
{
    public function normalize(Str $input): string
    {
        return trim($input->value());
    }
}

final class Cleaner
{
    public function clean(string $input): string
    {
        return str_replace('obsolete', '', $input);
    }
}

final class Formatter
{
    public function rebuild(string $input): Str
    {
        return new Str($input);
    }
}

final class Pipeline
{
    public function __construct(private Producer $producer, private Normalizer $normalizer, private Cleaner $cleaner, private Formatter $formatter) {}

    public function handle(string $input): Str
    {
        $text = $this->producer->load($input);
        $normalized = $this->normalizer->normalize($text);
        $cleaned = $this->cleaner->clean($normalized);

        return $this->formatter->rebuild($cleaned);
    }
}

```

The next fragment illustrates a possible scalar-processing boundary. It is not
a complete replacement for the pipeline or an automatic fix. Review the domain
contract and all callers before changing the representation boundary.

```php
// Design alternative to review in context
final class Cleaner
{
    public function clean(string $input): string
    {
        return str_replace('obsolete', '', trim($input));
    }
}
```

A candidate is only produced when extraction and reconstruction are actually connected through call paths: without a consumer such as `Pipeline::handle()` that wires `load()` → `normalize()` → `clean()` → `rebuild()` together, the declarations above stay disconnected and nothing is reported. The round trip typically spans several files, and each step must be provable: known scalar callables (`trim()`, `str_replace()`) and constructors with a direct, unconditional assignment to the stored field.

Disconnected flows are not reported: an overwritten value, independent calls of one helper, or reconstruction slots fed from different instances cannot close a round trip. Candidates are grouped per representation with their extraction and reconstruction locations and the connecting path, so review can start at the reported path instead of the whole codebase.

## Scope and exceptions

This is a research check, not a gate: it reports structural witnesses, never violations. A candidate is retained only when the detector can prove a confirmed data dependency from extraction to reconstruction of the same representation type through known scalar callables, typically spanning several files. Unresolved semantics (branch merges, references, unknown effects, virtual dispatch, lazy execution) remain visible as `partial` entries; concept-changing calls such as `strlen()` or `md5()` are listed under `excluded`. The report also discloses analysis coverage, budget limits, and callable resolution profiles.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `maxDepth` | `12` | Maximum nested callable expansions per witness. |
| `maxSteps` | `2000` | Maximum interpreted IR operations across the run. |
| `maxCandidates` | `100` | Maximum distinct retained reconstruction witnesses. |

All limits must be positive; exhausting one is disclosed explicitly as truncation in the report rather than silently dropping paths.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\RepresentationChurn;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepresentationChurn(maxDepth: 12, maxSteps: 2000, maxCandidates: 100),
    ]);
```

## Related rules

- [only_list_arrays](only_list_arrays.md) — another signal that the chosen data representation deserves a second look.
- [no_trivial_delegating_classes](no_trivial_delegating_classes.md) — round-trip paths often pass through classes that only forward values between steps.
