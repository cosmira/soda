# avoid_redundant_naming

📝 Detects class and method names that repeat words already supplied by the class or namespace, and suggests the shorter name.

💼 This rule is enabled in the ✅ `standard` config.

## Why

When a method lives inside `PostCollection`, a name like `addPost` repeats context the reader already has. Class names can also contain a redundant middle word before a generic suffix. For example, `Item` adds nothing to `PostCollection` in `PostItemCollection`. Repeated context makes identifiers longer and hides the action.

The rule compares each public method name with its owning class and parameter types. It also checks class names for redundant words before `Collection`, `Repository`, `Service`, `Manager`, `Factory`, `Handler`, or `Provider`. When the name is largely reproducible from that context, it reports the shorter form: `addPost` becomes `add`, and `PostItemCollection` becomes `PostCollection`.

Methods marked with `#[\Override]` remain accepted. Methods implementing a parent, interface, or abstract trait contract are also accepted because that contract fixes their names.

Method suggestions also preserve unresolved ancestor contracts: an unavailable
declaration does not establish that a public method name is free to change. A
suggested name must not collide with another owned method, a concrete visible
inherited method, or an existing type in the same namespace.

Parameter-type shortcuts compare the complete method suffix. A matching word
inside a longer suffix does not make its other words redundant. A prefix such as
`addMethod(MethodResult $method)` may repeat the input type, while
`addReturnType(Type $type)` keeps `Return`: the type does not supply that role.
Likewise, a `Route` input does not make `Middleware` redundant in
`attachMiddlewareToRoute`.

Collection shortcuts require a complete `All` or `List` word and a native array
or iterable return type. An unspecified return type does not prove a collection,
and substrings in `Fallback`, `Callback` or `Listeners` do not supply that word.

## Examples

```php
// ❌
final class PostItemCollection
{
    public function addPost(Post $post): void {}
    public function hasPost(Post $post): bool { return false; }
    public function clearPost(): void {}
}

// ✅
final class PostCollection
{
    public function add(Post $post): void {}
    public function has(Post $post): bool { return false; }
    public function clear(): void {}
}
```

```php
// ❌ "Item" is redundant before the generic "Repository" suffix
final class PostItemRepository {}

// ✅ the redundant middle word is removed
final class PostRepository {}
```

## Options

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `$threshold` | `int` | `80` | Similarity percentage (1–100) between the current name and the name rebuilt from its context that is needed to report a finding. Values outside 1–100 fall back to 80. Lower it to catch weaker redundancies; raise it to report only near-identical names. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new AvoidRedundantNaming(80),
    ]);
```

## Related rules

- [variable_name_length](variable_name_length.md) — bounds local identifier length without context analysis.
- [method_name_length](method_name_length.md) — flags sentence-like method names regardless of redundancy.
