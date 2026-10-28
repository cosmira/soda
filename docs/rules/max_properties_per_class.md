# max_properties_per_class

📝 Limits the number of properties declared in a single class.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

A class with many properties carries many pieces of state. Each property widens the set of combinations the class must keep consistent. The rule reports classes whose declared property count exceeds the limit. Keep state with its behavior and group values when they share meaning or invariants. A property count alone does not justify another object.

## Examples

```php
// ❌ seven loosely related properties in one class
final class OrderForm
{
    public function __construct(
        private string $number,
        private string $buyerEmail,
        private string $currency,
        private int $amount,
        private ?string $discountCode,
        private bool $expressShipping,
        private ?string $note,
    ) {}
}
```

```php
// ✅ related values travel together
final class OrderForm
{
    public function __construct(
        private readonly Buyer $buyer,
        private readonly Amount $amount,
        private readonly Shipping $shipping,
        private readonly ?string $note,
    ) {}
}
```

## Options

- `limit` (default `5`): maximum accepted number of declared properties per class. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxPropertiesPerClass(5),
    ]);
```

## Related rules

- [max_arguments](max_arguments.md) — uses this property limit to decide when a readonly data constructor counts as an accepted value object.
- [max_methods_per_class](max_methods_per_class.md) — broad state usually comes with a broad method surface.

