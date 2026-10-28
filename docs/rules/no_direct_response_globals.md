# no_direct_response_globals

📝 Reports `header()`, `setcookie()`, `setrawcookie()`, and `session_start()` calls that mutate response or session state outside an explicit boundary.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Sending headers, cookies, or opening sessions from domain code hides output effects. A method may claim to return a value while also mutating process-global response state. That behavior is difficult to test or reuse outside a web request. The rule reports each call at its source position. Return an explicit response object and isolate headers, cookies, and sessions in adapters.

## Examples

```php
// ❌
final class CacheAwareResponder
{
    public function render(string $payload): string
    {
        header('Cache-Control: private');

        return $payload;
    }
}

// ✅
final class CacheAwareResponder
{
    public function render(string $payload): Response
    {
        return new Response($payload, headers: ['Cache-Control' => 'private']);
    }
}
```

```php
// ❌
public function remember(string $tenant): string
{
    setcookie('tenant', $tenant);

    return $tenant;
}

// ✅
public function remember(string $tenant): Affinity
{
    return new Affinity(cookie: new Cookie('tenant', $tenant));
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoDirectResponseGlobals;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoDirectResponseGlobals(),
    ]);
```

## Related rules

- [no_global_state](no_global_state.md) — flags the other process-global state channels.
- [no_process_configuration_mutation](no_process_configuration_mutation.md) — flags runtime changes to ini, locale, and timezone.
- [no_environment_reads](no_environment_reads.md) — moves request-adjacent environment access behind a boundary.
