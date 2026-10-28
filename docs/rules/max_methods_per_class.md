# max_methods_per_class

📝 Limits the number of methods declared in a single class.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A class with dozens of methods often owns several responsibilities, spreading changes across unrelated scenarios. The rule reports each class whose declared method count exceeds the limit. Review the public scenario and unrelated responsibilities. A cohesive concern may stay on the owner as a trait; a new object needs a distinct purpose.

## Examples

```php
// ❌ 41 declared methods in one class
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
    public function import(Invoice $invoice): void {}
    public function validate(Invoice $invoice): void {}
    public function recalculate(Invoice $invoice): void {}
    public function applyDiscount(Invoice $invoice): void {}
    public function applySurcharge(Invoice $invoice): void {}
    public function refund(Invoice $invoice): void {}
    public function credit(Invoice $invoice): void {}
    public function charge(Invoice $invoice): void {}
    public function capture(Invoice $invoice): void {}
    public function void(Invoice $invoice): void {}
    public function split(Invoice $invoice): void {}
    public function merge(Invoice $invoice): void {}
    public function attach(Invoice $invoice): void {}
    public function detach(Invoice $invoice): void {}
    public function tag(Invoice $invoice): void {}
    public function untag(Invoice $invoice): void {}
    public function translate(Invoice $invoice): void {}
    public function preview(Invoice $invoice): void {}
    public function render(Invoice $invoice): void {}
    public function print(Invoice $invoice): void {}
    public function mail(Invoice $invoice): void {}
    public function schedule(Invoice $invoice): void {}
    public function escalate(Invoice $invoice): void {}
    public function lock(Invoice $invoice): void {}
    public function unlock(Invoice $invoice): void {}
    public function annotate(Invoice $invoice): void {}
    public function reconcile(Invoice $invoice): void {}
    public function report(Invoice $invoice): void {} // 41 methods total
}

```php
// ✅ responsibilities split by purpose
final class InvoiceService
{
    public function create(): Invoice {}
    public function send(Invoice $invoice): void {}
}

final class InvoiceReminder
{
    public function remind(Invoice $invoice): void {}
}
```

## Options

- `limit` (default `40`): maximum accepted number of declared methods per class. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxMethodsPerClass;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxMethodsPerClass(40),
    ]);
```

## Related rules

- [max_effective_methods_per_class](max_effective_methods_per_class.md) — counts the same surface including trait-contributed methods.
- [max_public_methods](max_public_methods.md) — bounds the part of the surface that callers actually depend on.
- [max_class_length](max_class_length.md) — bounds the source size that many methods inflate.

