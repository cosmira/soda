# no_service_locator_calls

📝 Declare dependencies in the constructor instead of pulling them from the global container with `app()` or `resolve()`.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

Calling `app()` or `resolve()` hides a piece of code's collaborators. Readers cannot see the dependency graph, services appear only at runtime, and the code cannot be constructed or tested without the container. The rule reports calls to the global functions `app` and `resolve`, including case-insensitive and fully qualified calls. Declare the dependencies in the constructor instead. Method calls with the same names are unaffected.

## Examples

```php
// ❌ Dependencies appear only at runtime
final class InvoiceSender
{
    public function send(Invoice $invoice): void
    {
        $mailer = app(Mailer::class);
        $billing = resolve(Billing::class);

        $billing->charge($invoice);
        $mailer->deliver($invoice);
    }
}
```

```php
// ✅ Dependencies declared where they are required
final class InvoiceSender
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly Billing $billing,
    ) {}

    public function send(Invoice $invoice): void
    {
        $this->billing->charge($invoice);
        $this->mailer->deliver($invoice);
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoServiceLocatorCalls;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoServiceLocatorCalls(),
    ]);
```

## Related rules

- [no_singleton_access](no_singleton_access.md) — flags the other global acquisition pattern: pulling an instance from a static accessor.
- [no_global_state](no_global_state.md) — flags global variable access, the raw form of reaching outside the local context.
