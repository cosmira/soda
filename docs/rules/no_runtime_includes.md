# no_runtime_includes

📝 Use autoloaded collaborators or validated data configuration instead of including files at runtime.

💼 This rule is enabled in the ✅ `standard` config.

## Why

A runtime `include` or `require` hides an executable dependency. Autoloaders and static analysis cannot see the loaded file, its path may be computed, and loading it can execute arbitrary code. The rule reports every `include`, `require`, `include_once`, and `require_once` expression. Rely on autoloaded classes, or use validated data files when configuration is the actual need.

## Examples

```php
// ❌ Executable behavior loaded from a computed path
final class ConventionPolicyLoader
{
    public function load(): string
    {
        return include __DIR__.'/runtime-policy.inc';
    }
}
```

```php
// ✅ Autoloaded collaborator, or validated data
final class PolicyLoader
{
    public function load(): Policy
    {
        return new Policy($this->settings->get('policy'));
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoRuntimeIncludes;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoRuntimeIncludes(),
    ]);
```

## Related rules

- [no_runtime_code_evaluation](no_runtime_code_evaluation.md) — flags `eval`, the inline variant of executing generated behavior.
- [no_native_deserialization](no_native_deserialization.md) — requires untrusted data to be decoded into explicitly constructed types.
