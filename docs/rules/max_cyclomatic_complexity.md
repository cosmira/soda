# max_cyclomatic_complexity

📝 Limits the number of independent branches a single method may contain.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A method with many independent branches has many paths to review and test. This
rule reports methods above the configured branch limit. Extract a named step
when it represents work that a reader can understand separately.

## Examples

```php
// ❌ complexity 9 (1 + eight decisions)
final class OrderPricer
{
    public function price(Order $order): int
    {
        $total = $order->total();

        if ($order->isPriority()) {                       // +1
            $total += 10;
        }

        if ($order->isInternational()) {                  // +1
            $total += 25;
        }

        foreach ($order->items() as $item) {              // +1
            if ($item->fragile()) {                       // +1
                $total += 5;
            }

            if ($item->oversized()) {                     // +1
                $total += 15;
            }

            if ($item->hazmat()) {                        // +1
                $total += 30;
            }

            if ($item->perishable()) {                    // +1
                $total += 20;
            }

            if ($item->requiresSignature()) {             // +1
                $total += 12;
            }

            $total += $item->tax();
        }

        return $total;
    }
}
```

The revised method delegates each item's charges without removing a surcharge,
tax, iteration, or condition. Both versions read the order and its items in the
same order.

```php
// ✅ price has complexity 4; itemCharges has complexity 6
final class OrderPricer
{
    public function price(Order $order): int
    {
        $total = $order->total();

        if ($order->isPriority()) {
            $total += 10;
        }

        if ($order->isInternational()) {
            $total += 25;
        }

        foreach ($order->items() as $item) {
            $total += $this->itemCharges($item);
        }

        return $total;
    }

    private function itemCharges(OrderItem $item): int
    {
        $total = 0;

        if ($item->fragile()) {
            $total += 5;
        }

        if ($item->oversized()) {
            $total += 15;
        }

        if ($item->hazmat()) {
            $total += 30;
        }

        if ($item->perishable()) {
            $total += 20;
        }

        if ($item->requiresSignature()) {
            $total += 12;
        }

        return $total + $item->tax();
    }
}
```

## Scope and exceptions

Every `if`, `elseif`, loop, `case`, `catch`, boolean operator, and ternary adds a
path to the base score of one. Guard clauses can reduce nesting without reducing
this branch count. A lower score does not prove that behavior is correct; retain
all calculations and observable effects when refactoring.

## Options

- `limit` (default `8`): maximum accepted cyclomatic complexity per method. A non-positive limit disables the check.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new MaxCyclomaticComplexity(8),
    ]);
```

## Related rules

- [max_cognitive_complexity](max_cognitive_complexity.md) — measures how hard the same branches are to read, weighted by nesting.
- [max_control_nesting](max_control_nesting.md) — bounds the depth of the branches that inflate this score.
- [max_statements_per_callable](max_statements_per_callable.md) — bounds total size independently of branching.
