# no_static_mutable_properties

📝 Put shared mutable state in an injected object with explicit lifetime and ownership instead of static properties.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

A static property or `static` local variable is process-wide mutable state. Every caller shares one storage location, writes are invisible at other call sites, and tests observe whatever previous tests left behind. The rule reports static property declarations and `static` local variable declarations. Move the state behind an injected object with explicit lifetime and ownership.

## Examples

```php
// ❌ A process-wide identity map inside the class
final class CustomerRepository
{
    /** @var array<string, string> */
    private static array $customers = [];

    public function save(string $email): void
    {
        self::$customers[$email] = $email;
    }
}
```

```php
// ❌ A static local is the same shared storage with a narrower scope
final class ExchangeRateRepository
{
    public function rate(string $pair): string
    {
        static $rates = [];
        $rates[$pair] ??= '1.00';

        return $rates[$pair];
    }
}
```

```php
// ✅ State owned by an injected instance with an explicit lifetime
final class CustomerRepository
{
    /** @var array<string, string> */
    private array $customers = [];

    public function save(string $email): void
    {
        $this->customers[$email] = $email;
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoStaticMutableProperties;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoStaticMutableProperties(),
    ]);
```

## Related rules

- [no_global_state](no_global_state.md) — flags global variable access, the rawest form of shared state.
- [no_singleton_access](no_singleton_access.md) — flags the static accessor that hands out the shared instance.
- [no_dynamic_properties](no_dynamic_properties.md) — requires instance state to have a declared, stable shape.
