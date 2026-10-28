# no_process_configuration_mutation

📝 Do not mutate ini settings, working directory, locale, or timezone from analyzed code; configure the runtime through `php.ini` or deployment configuration.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Calling `ini_set`, `chdir`, `setlocale`, or `date_default_timezone_set` changes configuration shared by the whole process. Other collaborators silently receive those changes, so behavior depends on which code ran first. The rule reports calls to these four functions wherever they appear in analyzed code, with no bootstrap exemption. Configure the runtime through `php.ini` or deployment configuration, then inject the resulting settings as typed values.

## Examples

```php
// ❌ Every helper in the process now formats numbers differently
final class MonetaryLocalePolicy
{
    public function activate(): string
    {
        setlocale(LC_NUMERIC, 'C');

        return 'locale-ready';
    }
}
```

```php
// ❌ The same hidden mutation for precision, cwd, and timezone
final class PrecisionPolicyManager
{
    public function apply(): string
    {
        ini_set('precision', '14');

        return 'precision-ready';
    }
}
```

```php
// ✅ The timezone arrives as injected configuration instead of a runtime mutation
final class ReportClock
{
    public function __construct(private readonly string $timezone) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(timezone: new DateTimeZone($this->timezone));
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoProcessConfigurationMutation;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoProcessConfigurationMutation(),
    ]);
```

## Related rules

- [no_runtime_hooks](no_runtime_hooks.md) — flags the other process-wide side effect installed inside application code.
- [no_environment_reads](no_environment_reads.md) — requires environment reads to happen once at the boundary and be injected as typed configuration.
- [no_global_state](no_global_state.md) — flags the general pattern of reaching outside the local context.
