# repeated_owner_concern

📝 Finds an identical group of stateful methods copied across independent owners and suggests sharing it as a concern (trait).

💼 This rule is enabled in the ✅ `standard` config.

## Why

When archive/restore/inspect behavior is copied across owners, a fix in one copy can diverge from the others. The rule reports copied stateful method groups for review. Extract a shared concern when the copies express one behavior contract, and document the fields each owner must declare.

The rule groups methods within each owner by shared fields and resolved local calls. Its fingerprint requires matching method names, visibility, parameter and return contracts, bodies, field contracts and defaults, and declaration order. Local variable names are normalized.

A group needs at least `minMethods` methods, at least two public methods, at least one state write, and at least one decision or calculation. Plain getters and setters are not a concern. Owners with inheritance, contracts, traits, or unresolved members are excluded. Already-shared traits are not reported.

## Examples

```php
// ❌
final class Post
{
    private bool $archived = false;

    public function archive(): void { $this->archived = true; }
    public function restore(): void { $this->archived = false; }
    public function isActive(): bool { return ! $this->archived; }
}

final class Comment
{
    private bool $archived = false;

    public function archive(): void { $this->archived = true; }
    public function restore(): void { $this->archived = false; }
    public function isActive(): bool { return ! $this->archived; }
}

// ✅
trait Archivable
{
    private bool $archived = false;

    public function archive(): void { $this->archived = true; }
    public function restore(): void { $this->archived = false; }
    public function isActive(): bool { return ! $this->archived; }
}

final class Post
{
    use Archivable;
}

final class Comment
{
    use Archivable;
}
```

Copies that differ in behavior, state contract, or meaning stay independent: changing `$active && ! $this->archived` in one owner, or declaring `int $archived` instead of `bool`, removes the finding.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `minMethods` | `3` | Minimum substantive methods in a copied group. Must be at least `3`. |
| `minOwners` | `2` | Minimum independent owners sharing the identical group. Must be at least `2`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\RepeatedOwnerConcern;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepeatedOwnerConcern(minMethods: 3, minOwners: 2),
    ]);
```

## Related rules

- [scattered_owner_concern](scattered_owner_concern.md) — the same substantive-group criteria applied to a group that is interleaved within a single owner.
- [fragmented_owner_behavior](fragmented_owner_behavior.md) — owner behavior spread across operation classes instead of copied between owners.
- [max_traits_per_class](max_traits_per_class.md) — bounds how many concerns a single class may compose.
