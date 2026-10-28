# prefer_string_starts_ends_with

📝 Reports substr comparisons that ask the prefix or suffix question.

💼 This rule is enabled in the ✅ `standard` config.

## Why

`substr($text, 0, 3) === 'sku'` answers a yes-or-no question by cutting the string first. `str_starts_with($text, 'sku')` answers it directly. `str_ends_with()` covers the suffix, and the `mb_*` variants handle multibyte subjects. The rule reports `substr()` and `mb_substr()` compared with a string literal using `===`, `!==`, `==`, or `!=` when their offsets match the literal. Offset zero with a matching length asks the prefix question. A negative offset with no length or a matching length asks the suffix question.

Middle substrings, comparisons with variables or other calls, and calls whose length does not match the literal stay outside the policy, because `str_starts_with()` and `str_ends_with()` would not answer them.

## Examples

```php
// ❌ a cut that only serves the comparison
if (substr($reference, 0, 4) === 'INV-') {
    $this->routeInvoice($reference);
}

// ✅ the question reads as a question
if (str_starts_with($reference, 'INV-')) {
    $this->routeInvoice($reference);
}
```

```php
// ❌ the suffix is cut by hand
if (substr($path, -4) === '.php') {
    $this->parse($path);
}

// ✅ one call carries the intent
if (str_ends_with($path, '.php')) {
    $this->parse($path);
}
```

## Configuration

```php
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Usage\PreferStringStartsEndsWith;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new PreferStringStartsEndsWith(),
    ]);
```

## Related rules

- [prefer_includes](prefer_includes.md) — applies the same question shape to substring membership.
- [no_numeric_array_index](no_numeric_array_index.md) — keeps positional access from hiding named questions elsewhere.
