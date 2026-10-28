# no_runtime_type_aliases

📝 Migrate callers to the real type or declare a visible adapter instead of aliasing class names at runtime with `class_alias`.

💼 This rule is enabled in the ✅ `standard` config.

## Why

`class_alias` changes the global symbol table so a second name resolves to an existing class. Readers and static tools see two unrelated types, while old call sites keep working under an alias. The mapping exists only at runtime and can hide deprecations. The rule reports every call to `class_alias`. Migrate callers to the real type, or introduce a visible adapter when a compatibility name is required.

## Examples

```php
// ❌ A hidden second name for the same implementation
final class CanonicalBillingService
{
    public function status(): string
    {
        return 'ready';
    }
}

class_alias(CanonicalBillingService::class, 'LegacyBillingManager');
```

```php
// ✅ An explicit adapter names the compatibility contract
final class LegacyBillingManager
{
    public function __construct(private readonly CanonicalBillingService $billing) {}

    public function status(): string
    {
        return $this->billing->status();
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoRuntimeTypeAliases;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoRuntimeTypeAliases(),
    ]);
```

## Related rules

- [no_runtime_includes](no_runtime_includes.md) — flags the other runtime modification of what a symbol resolves to.
- [no_reflection](no_reflection.md) — flags the reflective construction that usually accompanies legacy aliasing.
