# max_public_methods

📝 Limits the number of public methods a class exposes, including inherited and trait-composed ones.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Every public method is part of the contract callers depend on. Publishing more operations makes a class harder to change or test and exposes behavior that could remain internal. The rule counts the effective public surface: declared methods, public methods inherited from parents, and public methods contributed by traits. It reports classes above the limit. Review which operations callers need. Supporting behavior can remain private on its owner, while useful fluent operations and framework contracts may be preserved.

## Examples

```php
// ❌ 21 public methods, most only used internally
final class InvoiceService
{
    public function create(Invoice $invoice): void {}
    public function send(Invoice $invoice): void {}
    public function remind(Invoice $invoice): void {}
    public function duplicate(Invoice $invoice): void {}
    public function cancel(Invoice $invoice): void {}
    public function archive(Invoice $invoice): void {}
    public function restore(Invoice $invoice): void {}
    public function finalize(Invoice $invoice): void {}
    public function approve(Invoice $invoice): void {}
    public function reject(Invoice $invoice): void {}
    public function hold(Invoice $invoice): void {}
    public function release(Invoice $invoice): void {}
    public function export(Invoice $invoice): void {}
    public function validate(Invoice $invoice): void {}
    public function recalculate(Invoice $invoice): void {}
    public function refund(Invoice $invoice): void {}
    public function credit(Invoice $invoice): void {}
    public function void(Invoice $invoice): void {}
    public function split(Invoice $invoice): void {}
    public function merge(Invoice $invoice): void {}
    public function render(Invoice $invoice): void {} // 21 public methods total
}

```php
// ✅ a small public scenario, helpers stay private
final class InvoiceService
{
    public function create(): Invoice {}

    public function send(Invoice $invoice): void {}

    private function recalculateTotals(Invoice $invoice): void {}
}
```

## Options

- `limit` (default `20`): maximum accepted number of public methods per class, counted across the class, its parents and its traits. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxPublicMethods;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxPublicMethods(20),
    ]);
```

## Related rules

- [max_methods_per_class](max_methods_per_class.md) — bounds the whole declared surface, public and private.
- [max_effective_methods_per_class](max_effective_methods_per_class.md) — counts the composed surface including trait methods.
- [unused_methods](unused_methods.md) — flags public methods that no caller actually uses.

