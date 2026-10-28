# no_dynamic_properties

📝 Reports the `#[AllowDynamicProperties]` attribute that licenses undeclared object state.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Dynamic properties let code attach arbitrary fields to an object at runtime. The class declaration no longer describes the object's shape. Readers, static analysis, and serialization must then depend on which code paths ran first. The rule reports each `#[AllowDynamicProperties]` attribute. Declare typed properties or hydrate a named value object with a stable shape instead.

## Examples

```php
// ❌
#[AllowDynamicProperties]
final class FlexiblePartnerRecord
{
    public function hydrate(string $reference): string
    {
        $this->reference = $reference;

        return $this->reference;
    }
}

// ✅ Declared typed state
final class PartnerRecord
{
    public function __construct(private readonly string $reference) {}

    public function reference(): string
    {
        return $this->reference;
    }
}
```

```php
// ❌
#[AllowDynamicProperties]
class ImportRow {}

$row = new ImportRow();
$row->email = 'user@example.com';

// ✅ Hydrate a named value object with a stable shape
final class ImportRow
{
    public function __construct(public readonly string $email) {}
}

$row = new ImportRow(email: 'user@example.com');
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoDynamicProperties;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoDynamicProperties(),
    ]);
```

## Related rules

- [no_behavior_magic_methods](no_behavior_magic_methods.md) — flags magic methods that hide the supported contract the same way.
- [no_static_mutable_properties](no_static_mutable_properties.md) — flags the other undeclared, unbounded storage channel.
