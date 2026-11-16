# no_nested_array_access

Reports chained array access deeper than the configured limit. Disabled by default.

## Why

Named objects can make nested data structures easier to understand.

## Examples

```php
// Violating code with maxDepth: 1
$value = $data['customer']['name'];

// Passing code
$value = $customer->name;
```

## Options

`maxDepth` defaults to `1`. Each chained array lookup adds one level.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoNestedArrayAccess;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([new NoNestedArrayAccess(maxDepth: 1)]);
```

## Related rules

- [only_list_arrays](only_list_arrays.md). Restricts arrays to list semantics.
