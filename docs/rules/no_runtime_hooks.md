# no_runtime_hooks

📝 Do not install global runtime hooks from analyzed code; keep lifecycle and error handling in infrastructure configured outside the analyzed paths.

💼 This rule is enabled in the ✅ `standard` config.

## Why

`register_shutdown_function` and `set_error_handler` change process-wide behavior. Any method can alter shutdown or error handling, while later registrations remain invisible at earlier call sites. The rule reports calls to both functions, including case-insensitive and fully qualified calls, with no bootstrap exemption. Register lifecycle and error handling once in infrastructure outside the analyzed paths, such as a front controller or framework bootstrap. Alternatively, replace global hooks with injected collaborators.

## Examples

```php
// ❌ A domain class installs a process-wide hook as a side effect
final class DeferredAuditBuffer
{
    /** @var list<string> */
    private array $events = [];

    public function defer(string $event): void
    {
        $this->events[] = $event;
        register_shutdown_function($this->flush(...));
    }

    public function flush(): void
    {
        echo implode(',', $this->events);
    }
}
```

```php
// ❌ An error handler installed mid-operation swallows warnings for everyone
final class LenientImportBoundary
{
    public function import(string $payload): string
    {
        set_error_handler(static fn (): bool => true);

        return 'imported:'.$payload;
    }
}
```

```php
// ✅ Error handling arrives as an injected collaborator instead of a global hook
final class LenientImportBoundary
{
    public function __construct(private readonly ErrorHandler $handler) {}

    public function import(string $payload): string
    {
        return $this->handler->run(fn (): string => parse($payload));
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoRuntimeHooks;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoRuntimeHooks(),
    ]);
```

## Related rules

- [no_process_configuration_mutation](no_process_configuration_mutation.md) — flags the other process-wide mutation performed inside application code.
- [no_error_suppression](no_error_suppression.md) — flags the `@` operator, the local variant of hiding failures.
