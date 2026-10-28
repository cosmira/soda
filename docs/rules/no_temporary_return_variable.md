# no_temporary_return_variable

📝 Forbids a local variable created only to return its value immediately.

💼 This rule is enabled in the ✅ `standard` config.

## Why

Assigning a variable on one line and returning it on the next can repeat an expression without adding useful context. For example, `$result = load(); return $result;` makes the reader track a binding used only once. The rule reports this assignment and advises returning the expression directly. Variables that are logged, transformed, captured by a closure, or used in a larger expression are accepted. Reference returns, `global` and `static` bindings, and assignments whose right-hand side uses the same variable are also accepted.

A relevant `@var` on the assignment is a type assertion, not a disposable alias.
Both a named annotation for the returned variable and an unambiguous nameless type
are accepted. An unrelated comment or annotation for another variable does not
suppress a finding. Try/finally bodies are excluded conservatively: the finalizer
can observe the local symbol table through `compact()` or other dynamic reads.

```php
// ✅ preserve the asserted type and the exception boundary
try {
    /** @var array<string, mixed> $config */
    $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

    return $config;
} catch (JsonException $exception) {
    throw new ConfigurationException('Invalid configuration', previous: $exception);
}
```

## Examples

```php
// ❌
function version(): int
{
    $result = $this->invoke('GetVersion');

    return $result;
}

// ✅
function version(): int
{
    return $this->invoke('GetVersion');
}
```

```php
// ✅ Accepted: the variable is also used elsewhere
function status(): int
{
    $result = load();
    logValue($result);

    return $result;
}
```

```php
// ✅ Accepted: the returned expression is not the variable itself
function total(): int
{
    $result = load();

    return $result + 1;
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoTemporaryReturnVariable;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new NoTemporaryReturnVariable(),
    ]);
```

## Related rules

- [useless_variable](useless_variable.md) — flags the broader case of variables that are direct copies of another variable.
- [no_assignment_in_return](no_assignment_in_return.md) — the inverse pattern: an assignment embedded in the `return` statement itself.
