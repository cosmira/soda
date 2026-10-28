# scattered_owner_concern

📝 Reports a cohesive stateful method group that is interleaved with unrelated methods in one owner and suggests grouping it together, possibly as a concern.

💼 This rule is enabled in the ✅ `standard` config.

## Why

When methods sharing state are spread across a class, the reader must reconstruct the behavior group between unrelated methods. The rule first proves a group connected through shared fields and resolved local calls. It requires at least one state write, one decision or calculation, and two public members. It reports that group only when another method sits between its first and last declarations.

A class that contains several cohesive, adjacent groups does not fail this rule. The advice is to group the methods together beside their state first; a concern (trait) is worth considering only when a separate file improves navigation. Extraction keeps the behavior on the owner conceptually and does not reduce effective method or length measurements.

## Examples

```php
// ❌
final class Post
{
    private bool $archived = false;

    public function archive(): void { $this->archived = true; }

    public function title(): string
    {
        return 'post';
    }

    public function restore(): void { $this->archived = false; }

    public function isActive(): bool { return ! $this->archived; }
}

// ✅
final class Post
{
    private bool $archived = false;

    public function archive(): void { $this->archived = true; }
    public function restore(): void { $this->archived = false; }
    public function isActive(): bool { return ! $this->archived; }

    public function title(): string
    {
        return 'post';
    }
}
```

Groups smaller than three methods, groups without state changes or decisions, and owners with unknown links (dynamic calls, inheritance, traits) are never reported.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `minMethods` | `3` | Minimum substantive methods in an owner group before the rule considers interleaving. Must be at least `3`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\ScatteredOwnerConcern;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new ScatteredOwnerConcern(minMethods: 3),
    ]);
```

## Related rules

- [repeated_owner_concern](repeated_owner_concern.md) — the same group criteria applied to a group copied across owners instead of scattered inside one.
- [max_effective_methods_per_class](max_effective_methods_per_class.md) — bounds the overall method count a concern extraction must still fit into.
- [fragmented_owner_behavior](fragmented_owner_behavior.md) — behavior pushed out of the owner entirely instead of merely out of order.
