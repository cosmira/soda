# max_class_length

📝 Limits the source length of a single class declaration.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A class that keeps growing usually owns more than one responsibility. Long types are harder to navigate and test in isolation. Changes can also collide with unrelated work in the same file. The rule reports each class whose logical source length exceeds the limit. Review the responsibilities together: keep related behavior local, organize a cohesive concern in a trait, or extract an object with its own state or boundary.

The measurement counts logical code lines inside the class body — physical lines that contain PHP tokens — so blank lines and comments do not inflate the count.

## Examples

```php
// ❌ one class accumulating unrelated responsibilities
final class OrderService extends BaseService
{
    public function place(Order $order): void { /* ... */ }

    public function exportToCsv(array $orders): string { /* ... */ }

    public function sendInvoiceEmail(Order $order): void { /* ... */ }

    public function calculateLoyaltyPoints(Order $order): int { /* ... */ }

    // ... hundreds of lines later
}

// ✅ each concern lives on its own owner
final class OrderService extends BaseService
{
    public function place(Order $order): void { /* ... */ }
}

final class OrderExporter
{
    public function exportToCsv(array $orders): string { /* ... */ }
}
```

## Options

- `limit` (default `500`): maximum accepted logical source lines per class — physical lines containing PHP tokens inside the class body, so blank lines and comments do not count. A class with 501 code lines is reported; a 500-line class passes. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxClassLength;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxClassLength(500),
    ]);
```

## Related rules

- [max_effective_class_length](max_effective_class_length.md) — measures the same size after trait composition, so moving source into a trait does not hide it.
- [max_method_length](max_method_length.md) — bounds the size of individual methods inside the class.
- [max_file_loc](max_file_loc.md) — caps the physical length of the whole file.
