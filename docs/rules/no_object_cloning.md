# no_object_cloning

📝 Construct a new value instead of copying an object with `clone`.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

`clone` copies private state without a declared protocol. Object properties retain shared references, and magic `__clone` side effects run without an explicit call. Readers cannot distinguish a deliberate deep copy from an accidental shallow one. This strict policy forbids every `clone` expression, including well-behaved value-copy APIs. The syntax alone does not prove unsafe copying. Fresh construction satisfies the policy. If your codebase relies on clone-based value APIs, choose a different rule selection.

## Examples

```php
// ❌ The copy's shared references and side effects are invisible
final class ImmutablePrototypeManager
{
    public function fork(object $prototype): object
    {
        return clone $prototype;
    }
}
```

```php
// ❌ A reasonable value API is still flagged — the syntax is the target
final class Options
{
    public function __construct(public readonly int $limit) {}

    public function limitedTo(int $limit): self
    {
        $copy = clone $this;

        return new self($limit);
    }
}
```

```php
// ✅ Fresh construction makes the new value's state explicit
final class Options
{
    public function __construct(public readonly int $limit) {}

    public function limitedTo(int $limit): self
    {
        return new self($limit);
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoObjectCloning;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoObjectCloning(),
    ]);
```

## Related rules

- [no_static_mutable_properties](no_static_mutable_properties.md) — flags the shared mutable state that cloning is often used to escape.
- [no_dynamic_properties](no_dynamic_properties.md) — requires declared, typed state so fresh construction has a stable shape.
