# boolean_methods_without_prefix

📝 Requires boolean-returning methods to read as questions by starting with an explicit predicate prefix such as `is`, `has`, or `should`.

💼🚫 This rule is **not** enabled by default. Add it to your `soda.php` config to enable it.

## Why

A method returning `bool` answers a yes/no question, so its name should read as one: `isRunning()` instead of `running()`. Without a predicate prefix, call sites lose that reading and `if ($app->running())` must be mentally translated.

The rule inspects methods whose declared return type is `bool` or includes `bool`, such as `bool|null`. It reports those without an allowed prefix. Methods inherited from a parent class or interface, and methods marked `#[\Override]`, are exempt because their names are fixed by an external contract. Common operation verbs such as `create`, `update`, `delete`, and `check` are also ignored because they may return a success flag.

## Examples

```php
// ❌
final class Application
{
    public function running(): bool { return true; }
    public function healthy(): bool|null { return null; }
}

// ✅
final class Application
{
    public function isRunning(): bool { return true; }
    public function isHealthy(): bool|null { return null; }
}
```

```php
// ❌ The name gives no hint of a boolean answer.
public function cacheAvailable(): bool { return false; }

// ✅
public function hasCache(): bool { return false; }
```

## Options

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `$ignore` | `string[]` | `[]` | Additional method names or `Class::method` references to skip, merged with the built-in verbs (`create`, `update`, `delete`, `execute`, `run`, `process`, `handle`, `dispatch`, `send`, `save`, `store`, `generate`, `build`, `check`). |
| `$prefix` | `string[]` | `[]` | Extra allowed prefixes, merged with the built-in ones (`is`, `has`, `can`, `should`, `does`, `was`, `are`, `do`, `did`, `will`, `try`). |

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new BooleanMethodPrefix(
            ignore: ['App\Application::runningUnitTests'],
            prefix: ['may'],
        ),
    ]);
```

## Related rules

- [method_name_length](method_name_length.md) — bounds method name length independently of the return type.
- [avoid_redundant_naming](avoid_redundant_naming.md) — removes context words that already appear in the owner's name.
