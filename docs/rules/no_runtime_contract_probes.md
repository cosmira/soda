# no_runtime_contract_probes

📝 Require an interface or typed value instead of discovering capabilities at runtime with `method_exists`, `property_exists`, or `is_callable`.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

Probing an object for methods, properties, or callability replaces a declared contract with runtime discovery. Each caller repeats the probes, and a missing capability becomes a runtime branch instead of a type error. The rule reports calls to `method_exists`, `property_exists`, and `is_callable`. Require an explicit interface or a typed value shape instead.

## Examples

```php
// ❌ The supported contract is discovered call by call
final class AdaptiveGatewayNegotiator
{
    public function negotiate(object $gateway): string
    {
        return method_exists($gateway, 'commit') ? 'rich' : 'compatible';
    }
}
```

```php
// ❌ Shapeless payloads probed for optional fields
final class FlexiblePayloadReader
{
    public function inspect(object $payload): string
    {
        return property_exists($payload, 'reference') ? 'rich' : 'compatible';
    }
}
```

```php
// ✅ The interface declares the capability up front
interface Commits
{
    public function commit(): void;
}

final class GatewayNegotiator
{
    public function negotiate(Commits $gateway): string
    {
        $gateway->commit();

        return 'rich';
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoRuntimeContractProbes;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoRuntimeContractProbes(),
    ]);
```

## Related rules

- [no_reflection](no_reflection.md) — flags the reflective inspection that capability probing usually grows into.
- [no_dynamic_invocation](no_dynamic_invocation.md) — flags runtime-selected callables, the invocation side of undiscovered contracts.
- [no_untyped_parameters](no_untyped_parameters.md) — requires declared parameter types, the positive version of the same contract.
