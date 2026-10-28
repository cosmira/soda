# repeated_foreign_state_decision

📝 Finds the same compound question about another object's state repeated in several consumers and suggests naming it as a predicate on the owner.

💼 This rule is enabled in the ✅ `standard` config.

## Why

When many consumers recompute `$post->isPublished() && ! $post->deleted`, the domain rule lives in the callers instead of on the object that owns the state, so a change to the rule means finding and editing every consumer. The rule reports such repeated expressions as a review signal and advises introducing a named owner predicate; a concern (trait) can organize related owner behavior.

The detector fingerprints the compound boolean expression exactly: only direct public field reads, proved public zero-argument getters (whose complete body returns one declared field), literals, comparisons, negation, and exact class constants are supported. It requires `minConsumers` distinct named methods or functions consuming the same local, uncomposed owner type. Receiver names are normalized but accessor identity and operand order stay significant; repeats within one consumer count once. External context, two different receivers, nullsafe or dynamic access, and unresolved getters are excluded.

## Examples

```php
// ❌
function show(Post $post): bool
{
    return $post->isPublished() && ! $post->deleted;
}

function notify(Post $post): bool
{
    return $post->isPublished() && ! $post->deleted;
}

function index(Post $post): bool
{
    return $post->isPublished() && ! $post->deleted;
}

// ✅
final class Post
{
    public function isPubliclyVisible(): bool
    {
        return $this->published && ! $this->deleted;
    }
}

function show(Post $post): bool
{
    return $post->isPubliclyVisible();
}
```

A single read such as `$post->deleted` is not reported. A question mixed with outside context, such as `$post->isPublished() && $context`, is also accepted, as is an expression over two different `Post` instances. The ❌ shape requires the owner type to be declared in the analyzed paths with the public members the expression reads. Here these are `$deleted` and the proved `isPublished()` getter. An undeclared owner type cannot prove the reads, so nothing is reported. The three consumers shown are the minimum at the default `minConsumers`.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `minConsumers` | `3` | Minimum independent consumers repeating the same question. Must be at least `2`. |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Design\RepeatedForeignStateDecision;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepeatedForeignStateDecision(minConsumers: 3),
    ]);
```

## Related rules

- [repeated_foreign_state_update](repeated_foreign_state_update.md) — the same distribution problem for arithmetic changes to foreign state instead of questions about it.
- [fragmented_owner_behavior](fragmented_owner_behavior.md) — whole owner operations spread across classes rather than a single question repeated.
- [max_ask_then_tell_patterns](max_ask_then_tell_patterns.md) — bounds asking a foreign object for state to make a decision, of which this is a repeated form.
