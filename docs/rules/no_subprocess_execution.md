# no_subprocess_execution

📝 Wrap external commands in an injected, typed gateway with explicit failures instead of calling `shell_exec`, `exec`, `system`, or `popen` inline.

💼 This rule is enabled in the ✅ `standard` config.

## Why

An inline subprocess hides a dependency on an external command. The type contract does not expose the command, its failure modes, or its platform assumptions. Tests must run the process, and failures arrive as strings, exit codes, or empty output. The rule reports calls to `shell_exec`, `exec`, `system`, and `popen`. Wrap the external capability in an injected, typed gateway that declares its failures.

## Examples

```php
// ❌ An operating-system dependency buried in a formatting class
final class NativeFormattingAdapter
{
    public function format(): string
    {
        return trim((string) shell_exec('printf working'));
    }
}
```

```php
// ❌ Exit codes and output buffers as an ad-hoc contract
final class CompatibleProcessGateway
{
    public function execute(): string
    {
        $lines = [];
        exec('printf working', $lines);

        return implode('', $lines);
    }
}
```

```php
// ✅ The capability lives behind an injected, typed port
final class Formatter
{
    public function __construct(private readonly CommandGateway $commands) {}

    public function format(): string
    {
        return $this->commands->run('printf working')->outputOrThrow();
    }
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoSubprocessExecution;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoSubprocessExecution(),
    ]);
```

## Related rules

- [no_direct_filesystem_mutation](no_direct_filesystem_mutation.md) — moves the other raw platform capability behind an injected port.
- [no_environment_reads](no_environment_reads.md) — requires platform reads to happen once at the boundary and be injected.
