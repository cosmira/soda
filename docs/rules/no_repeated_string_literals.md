# no_repeated_string_literals

📝 Reports string literals repeated several times as expressions within one class-like declaration.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Repeated strings couple several locations to one unnamed protocol value. Changing the value requires finding every copy, and a typo can fail silently. The rule groups equal strings within each class, trait, enum, interface, or anonymous class. It decodes escapes and heredocs, so `'ready'`, `"\x72eady"`, and a heredoc containing `ready` count as one value. Each group is reported at the occurrence that crosses the threshold. Name a repeated protocol value when it has one meaning. Literal array keys are excluded by default, and intentional repetitions can be listed explicitly.

## Examples

```php
// ❌ the status value is spelled out three times
final class Worker
{
    public function run(): void
    {
        send('ready');
        send('ready');
        send('ready');
    }
}

// ✅ the protocol value gets a named constant
final class Worker
{
    private const STATUS_READY = 'ready';

    public function run(): void
    {
        send(self::STATUS_READY);
        send(self::STATUS_READY);
        send(self::STATUS_READY);
    }
}
```

## Options

- `minOccurrences` (default `3`): minimum number of equal occurrences required for a finding. Must be at least `2`.
- `minLength` (default `3`): minimum byte length of a non-whitespace literal to consider. Must be positive.
- `ignoredStrings` (default `[]`): exact decoded strings explicitly accepted by the project.
- `includeArrayKeys` (default `false`): include literal array keys when enforcing the strict expression policy.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoRepeatedStringLiterals;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoRepeatedStringLiterals(
            minOccurrences: 3,
            minLength: 3,
            ignoredStrings: [],
            includeArrayKeys: false,
        ),
    ]);
```

## Related rules

- [no_repeated_compound_conditions](no_repeated_compound_conditions.md) — detects repetition of compound decisions instead of literal values.
- [avoid_redundant_naming](avoid_redundant_naming.md) — keeps the names you introduce for such values meaningful.
- [repeated_configuration_sequence](repeated_configuration_sequence.md) — detects repeated configuration statements at the method level.
