# no_empty_finally_blocks

📝 Reports `finally` blocks that contain no executable statements.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A `finally` block guarantees cleanup. An empty block, including a comment-only body, adds syntax without behavior and suggests cleanup that never happens. The rule reports empty finally blocks. If a `catch` is present, remove the empty block. Without a catch, removing only the `finally` is invalid PHP; unwrap the try body as well. Preserve any useful explanation when removing the block.

## Examples

```php
// ❌ finally with no cleanup
try {
    work();
} finally {
}

// ✅ the block is dropped entirely
try {
    work();
} catch (Exception $error) {
    recover($error);
}
```

```php
// ❌ try without catch cannot lose only its finally
try {
    work();
} finally {
    /* explanation only */
}

// ✅ unwrap the try when no catch is needed
work();
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoEmptyFinallyBlocks;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoEmptyFinallyBlocks,
    ]);
```

## Related rules

- [no_return_in_finally](no_return_in_finally.md) — forbids the other misuse of finally: returning from cleanup.
- [no_redundant_rethrow](no_redundant_rethrow.md) — reviews catches that only rethrow their bound exception.
- [max_try_catch_blocks](max_try_catch_blocks.md) — bounds how many try blocks one method may carry.
