# no_redundant_array_keys

Reports explicit integer keys in array literals whose keys already follow the implicit
`0, 1, 2, ...` sequence. Enabled in the standard bundle.

## Why

Sequential keys repeat PHP's automatic indexing. Omitting them makes the list easier
to read without changing its keys, values or insertion order.

## Examples

```php
// Violating code
$visibilities = [0 => 'public', 1 => 'protected', 2 => 'private'];

// Passing code
$visibilities = ['public', 'protected', 'private'];
```

Mixed implicit and explicit keys, such as `['public', 1 => 'protected']`, are also
reported when every explicit key equals its zero-based position. Each array produces
one finding, including nested arrays. Both `[]` and `array()` syntax are supported.

Arrays with gaps, reordered or duplicate keys, string keys, computed keys, or unpacking
are accepted. The rule only recognizes literal integer keys; it does not evaluate
expressions or coerce other key types. Destructuring is outside its scope.

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\NoRedundantArrayKeys;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([new NoRedundantArrayKeys()]);
```

There are no constructor options. The check reports findings and does not rewrite code.

## Related rules

- [no_numeric_array_index](no_numeric_array_index.md). Reviews repeated positional reads of documented tuples.
- [only_list_arrays](only_list_arrays.md). Reviews nested structured array access.
